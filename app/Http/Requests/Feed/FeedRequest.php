<?php

namespace App\Http\Requests\Feed;

use App\Enums\MealSlot;
use App\Models\Area;
use App\Services\Feed\FeedRadius;
use App\Support\Geo\Bangladesh;
use App\Support\Geo\GeoPoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeedRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:'.Bangladesh::MIN_LAT.','.Bangladesh::MAX_LAT],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:'.Bangladesh::MIN_LNG.','.Bangladesh::MAX_LNG],
            'area' => ['nullable', 'string', Rule::exists('areas', 'slug')->where('is_active', true)],
            'radius' => ['nullable', Rule::enum(FeedRadius::class)],
            'meal' => ['nullable', Rule::enum(MealSlot::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.between' => 'আপনার লোকেশন বাংলাদেশের ভেতরে পাওয়া যায়নি। এলাকা বেছে নিন।',
            'lng.between' => 'আপনার লোকেশন বাংলাদেশের ভেতরে পাওয়া যায়নি। এলাকা বেছে নিন।',
            'area.exists' => 'এই এলাকাটি এখন চালু নেই। আরেকটা এলাকা বেছে নিন।',
        ];
    }

    /** The buyer's own position wins; otherwise the chosen area's centre. */
    public function origin(): ?GeoPoint
    {
        if ($this->filled(['lat', 'lng'])) {
            return GeoPoint::make($this->input('lat'), $this->input('lng'));
        }

        return $this->area()?->center;
    }

    public function area(): ?Area
    {
        return $this->filled('area') ? Area::active()->where('slug', $this->input('area'))->first() : null;
    }

    public function radius(): FeedRadius
    {
        return FeedRadius::tryFrom((string) $this->input('radius')) ?? FeedRadius::ThreeKm;
    }

    public function meal(): ?MealSlot
    {
        return MealSlot::tryFrom((string) $this->input('meal'));
    }
}
