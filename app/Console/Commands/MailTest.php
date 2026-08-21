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

            return self::FAILURE;
        }

        $this->components->info(
            $mailer === 'log'
                ? 'Written to the log — nothing was actually sent.'
                : 'Accepted by the mail server. Check Mailgun → Logs for the delivery result.'
        );

        return self::SUCCESS;
    }
}
