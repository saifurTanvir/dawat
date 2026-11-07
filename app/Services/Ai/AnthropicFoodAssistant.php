<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Claude via the Messages API. Answers come back through a strict tool so they
 * are always JSON of a known shape; tool_choice stays "auto" because newer
 * models reject forced tool choice, and the prompt asks for the tool call.
 */
class AnthropicFoodAssistant implements FoodAssistant
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function available(): bool
    {
        return filled(config('services.ai.anthropic_key'));
    }

    public function suggestFromPhoto(string $jpeg, array $tags): FoodSuggestion
    {
        $tagList = collect($tags)->map(fn ($name, $slug) => "{$slug} = {$name}")->implode("\n");

        $input = $this->call(
            system: <<<'TXT'
                You help home cooks in Dhaka post the food they cooked today for neighbours to buy.
                Look at the photo and describe ONLY what is visibly on the plate. Never guess hidden
                ingredients, never praise, never invent. Write the dish name in simple Bangla the way a
                Dhaka home cook would (e.g. "ভাত, মুরগির ঝোল, ডাল"), at most 60 characters.
                Choose tags only from the given list and only when the photo clearly supports them;
                when unsure, choose none. Never choose "halal", "diabetic-friendly" or allergen tags:
                only the cook can know those. If the photo is not food, is very blurry or too dark,
                set photo_ok to false and explain the problem in one short Bangla sentence.
                Always answer by calling the describe_food tool.
                TXT,
            content: [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode($jpeg)]],
                ['type' => 'text', 'text' => "Tags you may choose (slug = name):\n{$tagList}"],
            ],
            tool: [
                'name' => 'describe_food',
                'description' => 'Report what food the photo shows.',
                'strict' => true,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => ['string', 'null'], 'description' => 'Dish name in Bangla, or null if not food.'],
                        'tag_slugs' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'photo_ok' => ['type' => 'boolean'],
                        'photo_problem' => ['type' => ['string', 'null'], 'description' => 'One short Bangla sentence, or null.'],
                    ],
                    'required' => ['title', 'tag_slugs', 'photo_ok', 'photo_problem'],
                    'additionalProperties' => false,
                ],
            ],
            maxTokens: 400,
        );

        $neverSuggest = ['halal', 'diabetic-friendly', 'contains-nuts', 'contains-shrimp'];
        $slugs = collect($input['tag_slugs'] ?? [])
            ->filter(fn ($s) => is_string($s) && array_key_exists($s, $tags) && ! in_array($s, $neverSuggest, true))
            ->unique()->values()->all();

        $title = is_string($input['title'] ?? null) ? Str::limit(trim($input['title']), 100, '') : null;

        return new FoodSuggestion(
            title: $title !== '' ? $title : null,
            tagSlugs: $slugs,
            photoOk: (bool) ($input['photo_ok'] ?? true),
            photoProblem: is_string($input['photo_problem'] ?? null) ? Str::limit(trim($input['photo_problem']), 160) : null,
        );
    }

    public function polishNote(string $note, string $title): string
    {
        $input = $this->call(
            system: <<<'TXT'
                You tidy a short note a home cook in Dhaka wrote for buyers about today's food.
                Fix spelling and make it clear and friendly in simple Bangla, at most 300 characters.
                Keep the cook's meaning and voice. You MUST keep every honest warning or detail the cook
                gave: less/more salt, spice, oil, turmeric, allergens, anything that did not turn out well.
                Never remove, soften or hide a warning. Never add claims (healthy, best, fresh, halal,
                organic) the cook did not make. Always answer by calling the polished_note tool.
                TXT,
            content: [['type' => 'text', 'text' => "Dish: {$title}\nCook's note: {$note}"]],
            tool: [
                'name' => 'polished_note',
                'description' => 'Return the tidied note.',
                'strict' => true,
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['note' => ['type' => 'string']],
                    'required' => ['note'],
                    'additionalProperties' => false,
                ],
            ],
            maxTokens: 500,
        );

        $polished = trim((string) ($input['note'] ?? ''));

        return $polished !== '' ? Str::limit($polished, 300, '') : $note;
    }

    /** @return array<string, mixed> the tool input */
    private function call(string $system, array $content, array $tool, int $maxTokens): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.ai.anthropic_key'),
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout((int) config('services.ai.timeout', 30))
                ->acceptJson()
                ->post(self::ENDPOINT, [
                    'model' => config('services.ai.model'),
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'tools' => [$tool],
                    'messages' => [['role' => 'user', 'content' => $content]],
                ]);
        } catch (ConnectionException $e) {
            throw new AssistantUnavailableException('AI service unreachable.', previous: $e);
        }

        if ($response->failed()) {
            Log::warning('AI request failed', ['status' => $response->status(), 'error' => $response->json('error.type')]);
            throw new AssistantUnavailableException('AI service error '.$response->status());
        }

        $toolUse = collect($response->json('content', []))
            ->first(fn ($block) => ($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $tool['name']);

        if (! $toolUse || ! is_array($toolUse['input'] ?? null)) {
            throw new AssistantUnavailableException('AI did not return a structured answer.');
        }

        return $toolUse['input'];
    }
}
