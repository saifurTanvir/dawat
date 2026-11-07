<?php

namespace App\Services\Ai;

/** What the assistant thinks a photo shows. Only ever a suggestion for the cook. */
final class FoodSuggestion
{
    /** @param  list<string>  $tagSlugs */
    public function __construct(
        public readonly ?string $title,
        public readonly array $tagSlugs,
        public readonly bool $photoOk,
        public readonly ?string $photoProblem,
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'tag_slugs' => $this->tagSlugs,
            'photo_ok' => $this->photoOk,
            'photo_problem' => $this->photoProblem,
        ];
    }
}
