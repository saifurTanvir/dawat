<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Support\ContactHours;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /** PUT /api/v1/me/contact-hours — F41: when the person may be called. */
    public function contactHours(Request $request): MeResource
    {
        $earliest = config('dawat.contact.earliest');
        $latest = config('dawat.contact.latest');
        $data = $request->validate([
            'from' => ['required', 'date_format:H:i', 'after_or_equal:'.$earliest],
            'to' => ['required', 'date_format:H:i', 'before_or_equal:'.$latest, 'after:from'],
            'prefer' => ['required', Rule::in(array_keys(ContactHours::PREFER_LABELS))],
        ], [
            'from.after_or_equal' => 'সকাল ৭টার আগে কেউ কল দেবে না, ৭টা বা পরে বাছুন।',
            'to.before_or_equal' => 'রাত ১০টার পরে কেউ কল দেবে না, ১০টা বা আগে বাছুন।',
            'to.after' => 'শেষের সময় শুরুর পরে হতে হবে।',
        ]);

        // At least two hours, so Dawat can always reach them about a box.
        [$fh, $fm] = array_map('intval', explode(':', $data['from']));
        [$th, $tm] = array_map('intval', explode(':', $data['to']));
        if (($th * 60 + $tm) - ($fh * 60 + $fm) < 120) {
            throw ValidationException::withMessages(['to' => 'কমপক্ষে ২ ঘণ্টা সময় রাখুন।']);
        }

        $request->user()->forceFill([
            'call_from' => $data['from'],
            'call_to' => $data['to'],
            'contact_prefer' => $data['prefer'],
        ])->save();

        return MeResource::make($request->user()->refresh());
    }
}
