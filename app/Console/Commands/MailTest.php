<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class MailTest extends Command
{
    protected $signature = 'mail:test {to : The recipient email address}';

    protected $description = 'Send a test email through the configured mailer';

    public function handle(): int
    {
        $to = $this->argument('to');

        if (Validator::make(['to' => $to], ['to' => 'required|email'])->fails()) {
            $this->components->error("Not a valid email address: {$to}");

            return self::FAILURE;
        }

        $mailer = config('mail.default');

        $this->components->twoColumnDetail('Mailer', $mailer);

        if ($mailer === 'smtp') {
            $this->components->twoColumnDetail(
                'Server',
                config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port')
                    .' ('.(config('mail.mailers.smtp.scheme') ?: 'auto').')'
            );
            $this->components->twoColumnDetail('Username', config('mail.mailers.smtp.username') ?: '<empty>');
            $this->components->twoColumnDetail(
                'Password',
                config('mail.mailers.smtp.password') ? '<set>' : '<EMPTY — this will fail>'
            );
        }

        $this->components->twoColumnDetail('From', config('mail.from.address'));
        $this->components->twoColumnDetail('To', $to);
        $this->newLine();

        $sentAt = now()->toDateTimeString();

        try {
            Mail::raw(
                "Test email from ".config('app.name').".\n"
                ."Sent at {$sentAt} UTC via the [{$mailer}] mailer.\n\n"
                ."If you are reading this, outbound mail works.",
                fn ($message) => $message
                    ->to($to)
                    ->subject('Mail test — '.config('app.name'))
            );
        } catch (Throwable $e) {
            $this->components->error(class_basename($e).': '.$e->getMessage());

            if (str_contains($e->getMessage(), 'authenticate')) {
                $this->diagnoseCredentials();
            }

            return self::FAILURE;
        }

        $this->components->info(
            $mailer === 'log'
                ? 'Written to the log — nothing was actually sent.'
                : 'Accepted by the mail server. Check Mailgun → Logs for the delivery result.'
        );

        return self::SUCCESS;
    }

    /**
     * Explain a 535 without ever printing the secret itself.
     */
    private function diagnoseCredentials(): void
    {
        $user = (string) config('mail.mailers.smtp.username');
        $pass = (string) config('mail.mailers.smtp.password');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow;options=bold>Credential check</>', '');

        $this->components->twoColumnDetail('Username length', (string) strlen($user));
        $this->components->twoColumnDetail(
            'Username has stray whitespace',
            $user !== trim($user) ? '<fg=red>YES — breaks auth</>' : 'no'
        );

        $this->components->twoColumnDetail('Password length', (string) strlen($pass));
        $this->components->twoColumnDetail(
            'Password has whitespace',
            preg_match('/\s/', $pass) === 1 ? '<fg=red>YES — value likely unquoted in .env</>' : 'no'
        );
        $this->components->twoColumnDetail(
            'Password has # or quote chars',
            preg_match('/[#"\']/', $pass) === 1 ? '<fg=red>YES — must be quoted in .env</>' : 'no'
        );
        $this->components->twoColumnDetail(
            'Config cached',
            app()->configurationIsCached()
                ? '<fg=yellow>YES — run config:clear after changing env</>'
                : 'no'
        );
        $this->components->twoColumnDetail('SMTP host', config('mail.mailers.smtp.host'));

        $this->newLine();
        $this->line('  A 535 with a non-empty password almost always means one of:');
        $this->line('   <fg=cyan>1.</> The credential belongs to the <options=bold>other</> Mailgun region (US vs EU).');
        $this->line('   <fg=cyan>2.</> The password was regenerated — Mailgun shows it only once.');
        $this->line('   <fg=cyan>3.</> The username is not the exact SMTP login shown in Mailgun.');
        $this->line('   <fg=cyan>4.</> Characters were lost to .env parsing — wrap the value in quotes.');
    }
}
