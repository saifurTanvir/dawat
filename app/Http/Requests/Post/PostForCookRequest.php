<?php

namespace App\Http\Requests\Post;

/**
 * F43: a post made for a cook (by her shahidar). Same fields as her own post,
 * but photos are optional: without them her last photos are reused.
 */
class PostForCookRequest extends StorePostRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isShahidar() ?? false;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(403);
    }
}
