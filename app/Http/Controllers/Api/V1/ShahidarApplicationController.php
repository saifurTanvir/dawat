<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shahidar\ApplyRequest;
use App\Services\Shahidars\ShahidarApplications;
use App\Support\ShahidarApplicationOptions as Options;
use Illuminate\Http\JsonResponse;

class ShahidarApplicationController extends Controller
{
    /** GET /api/v1/shahidar-applications/options — the choices on the form. */
    public function options(): JsonResponse
    {
        $list = fn (array $options) => collect($options)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values();

        return response()->json(['data' => [
            'gender' => $list(Options::GENDER),
            'job' => $list(Options::JOB),
            'vehicle' => $list(Options::VEHICLE),
            'needs_license' => Options::NEEDS_LICENSE,
            'hours' => $list(Options::HOURS),
            'slots' => $list(Options::SLOTS),
            'heard_from' => $list(Options::HEARD_FROM),
            'income' => config('dawat.shahidar.income'),
        ]]);
    }

    /** POST /api/v1/shahidar-applications — F53: apply to work as a shahidar. */
    public function store(ApplyRequest $request, ShahidarApplications $applications): JsonResponse
    {
        $data = $request->validated();

        if ($applications->isAlreadyShahidar($data['phone'])) {
            return response()->json([
                'message' => 'এই নম্বর আগে থেকেই দাওয়াতের শাহিদার। এই নম্বর দিয়ে লগইন করুন।',
                'errors' => ['phone' => ['এই নম্বর আগে থেকেই শাহিদার।']],
            ], 422);
        }

        [, $isNew] = $applications->submit($data);

        return response()->json([
            'message' => $isNew
                ? 'আবেদন পেয়েছি। দাওয়াত টিম শিগগিরই আপনাকে ফোন করবে।'
                : 'আপনার আগের আবেদনটা নতুন তথ্য দিয়ে হালনাগাদ করা হলো। দাওয়াত টিম শিগগিরই ফোন করবে।',
            'is_new' => $isNew,
        ], $isNew ? 201 : 200);
    }
}
