<?php

namespace App\Services\Ai;

/** AI_DRIVER=none: the app works exactly as before, the AI buttons are hidden. */
class NullFoodAssistant implements FoodAssistant
{
    public function available(): bool
    {
        return false;
    }

    public function suggestFromPhoto(string $jpeg, array $tags): FoodSuggestion
    {
        throw new AssistantUnavailableException('AI is off.');
    }

    public function polishNote(string $note, string $title): string
    {
        throw new AssistantUnavailableException('AI is off.');
    }
}
