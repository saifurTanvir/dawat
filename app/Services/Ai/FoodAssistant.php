<?php

namespace App\Services\Ai;

/**
 * F20: helps a cook write a post. Never edits or generates the food photo.
 */
interface FoodAssistant
{
    public function available(): bool;

    /**
     * @param  string  $jpeg  a small, metadata-free JPEG of the cook's photo
     * @param  array<string, string>  $tags  slug => Bangla name of the tags the cook may choose
     */
    public function suggestFromPhoto(string $jpeg, array $tags): FoodSuggestion;

    /** Tidies the cook's own note; must keep every warning (salt, spice, oil, allergens). */
    public function polishNote(string $note, string $title): string;
}
