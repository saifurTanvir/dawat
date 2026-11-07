<?php

namespace App\Services\ShareCards;

/** Turns the card's HTML into image bytes (JPEG). */
interface ShareCardRenderer
{
    public function render(string $html, int $width, int $height): string;
}
