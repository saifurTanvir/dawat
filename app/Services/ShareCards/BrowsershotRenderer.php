<?php

namespace App\Services\ShareCards;

use Spatie\Browsershot\Browsershot;

/**
 * Headless Chrome shapes Bangla correctly (conjuncts, vowel signs), which
 * PHP's GD and plain Imagick do not.
 */
class BrowsershotRenderer implements ShareCardRenderer
{
    public function render(string $html, int $width, int $height): string
    {
        $shot = Browsershot::html($html)
            ->setChromePath(config('dawat.share_cards.chrome_path'))
            ->setNodeBinary(config('dawat.share_cards.node_binary'))
            ->windowSize($width, $height)
            ->deviceScaleFactor(1)
            ->setScreenshotType('jpeg', 85)
            ->waitUntilNetworkIdle()
            ->timeout(60)
            ->noSandbox();

        if ($modules = config('dawat.share_cards.node_modules')) {
            $shot->setNodeModulePath($modules);
        }

        return $shot->screenshot();
    }
}
