<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /** GET /api/v1/me */
    public function show(Request $request): MeResource
    {
        return MeResource::make($request->user());
    }

    /** PATCH /api/v1/me — set a display name. */
    public function update(Request $request): MeResource
    {
        $data = $request->validate(
            ['name' => ['required', 'string', 'min:2', 'max:60']],
            ['name.required' => 'নাম দিন।', 'name.min' => 'নাম কমপক্ষে ২ অক্ষরের হতে হবে।'],
        );

        $request->user()->update($data);

        return MeResource::make($request->user());
    }
}
