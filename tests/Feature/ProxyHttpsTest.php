<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Coolify terminates TLS at its reverse proxy, so PHP receives plain HTTP with
 * an X-Forwarded-Proto header. Two separate pieces have to agree about the
 * scheme, and when they disagree Livewire file uploads fail with a bare
 * "failed to upload":
 *
 *   1. URL generation  -> URL::forceScheme() in AppServiceProvider
 *   2. Signed URL verification -> $request->getSchemeAndHttpHost(), which only
 *      reports https when the proxy is trusted (bootstrap/app.php)
 *
 * These tests pin both halves.
 */
class ProxyHttpsTest extends TestCase
{
    private const HOST = 'app.example.com';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://'.self::HOST]);
    }

    /**
     * A request as it actually arrives from the proxy: plain HTTP on the wire,
     * with the original scheme in a forwarded header.
     */
    private function proxiedRequest(string $url): Request
    {
        $request = Request::create($url, 'POST');

        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-For', '203.0.113.9');
        $request->server->set('REMOTE_ADDR', '172.18.0.5');

        return $request;
    }

    public function test_the_proxy_is_trusted_so_forwarded_requests_are_seen_as_secure(): void
    {
        $request = $this->proxiedRequest('http://'.self::HOST.'/livewire/upload-file');

        // Running the middleware stack is what applies the trustProxies config.
        $this->app->make(TrustProxies::class)
            ->handle($request, fn () => response());

        $this->assertTrue($request->isSecure(), 'X-Forwarded-Proto was ignored — is trustProxies configured?');
        $this->assertSame('https://'.self::HOST, $request->getSchemeAndHttpHost());
        $this->assertSame('203.0.113.9', $request->ip(), 'the real client IP should survive the proxy');
    }

    public function test_a_signed_url_generated_as_https_validates_behind_the_proxy(): void
    {
        URL::forceScheme('https');

        $signed = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5));

        $this->assertStringStartsWith('https://', $signed, 'signed URLs must be generated over https');

        // Rebuild the same URL as it reaches PHP: identical host and path, but
        // downgraded to http by the proxy.
        $parts = parse_url($signed);
        $onTheWire = 'http://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .$parts['path'].'?'.$parts['query'];

        $request = $this->proxiedRequest($onTheWire);

        $this->app->make(TrustProxies::class)
            ->handle($request, fn () => response());

        $this->assertTrue(
            URL::hasValidSignature($request),
            'the signature failed to validate — Livewire uploads would 401 here'
        );
    }

    public function test_url_generation_forces_https_when_app_url_is_https(): void
    {
        // AppServiceProvider::boot() applies this from config('app.url').
        $this->refreshApplication();
        config(['app.url' => 'https://'.self::HOST]);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', URL::to('/admin'));
    }
}
