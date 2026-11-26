<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The admin's round picture: the first letter of the name, drawn here. The
 * default (an outside image service) showed a broken image for Bangla names.
 */
class LetterAvatars implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));
        $letter = $name !== '' ? mb_substr($name, 0, 1) : '?';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#1e5b3f"/>'
            .'<text x="32" y="42" font-family="Noto Sans Bengali, sans-serif" font-size="30" fill="#fff" text-anchor="middle">'.htmlspecialchars($letter, ENT_XML1).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
