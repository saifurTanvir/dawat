<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** F37: creates the Web Push key pair once and stores it in the server's .env. */
#[Signature('dawat:webpush-keys {--env-file= : .env to add the keys to (default: this app\'s .env)}')]
#[Description('Add WEBPUSH_PUBLIC_KEY / WEBPUSH_PRIVATE_KEY to .env if they are missing')]
class WebPushKeys extends Command
{
    public function handle(): int
    {
        $file = $this->option('env-file') ?: base_path('.env');
        $env = is_file($file) ? file_get_contents($file) : '';

        if (preg_match('/^WEBPUSH_PUBLIC_KEY=\S+/m', $env) && preg_match('/^WEBPUSH_PRIVATE_KEY=\S+/m', $env)) {
            $this->info('Web Push keys already set.');

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();
        $env = preg_replace('/^WEBPUSH_(PUBLIC|PRIVATE)_KEY=.*\n?/m', '', $env);
        $env = rtrim($env)."\n\n# F37 Web Push (free notifications). Do not change: phones subscribed with this pair.\n"
            ."WEBPUSH_PUBLIC_KEY={$keys['publicKey']}\nWEBPUSH_PRIVATE_KEY={$keys['privateKey']}\n";
        file_put_contents($file, $env);
        $this->info("Web Push keys added to {$file}.");

        return self::SUCCESS;
    }
}
