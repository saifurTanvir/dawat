<?php

namespace App\Console\Commands;

use App\Support\Seo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** F31: nginx serves /robots.txt from disk, so each deploy writes it with the live domain. */
#[Signature('dawat:robots')]
#[Description('Write public/robots.txt with the sitemap address')]
class WriteRobots extends Command
{
    public function handle(): int
    {
        file_put_contents(public_path('robots.txt'), Seo::robotsTxt());
        $this->info('public/robots.txt written for '.url('/'));

        return self::SUCCESS;
    }
}
