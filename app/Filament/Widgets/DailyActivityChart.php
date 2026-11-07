<?php

namespace App\Filament\Widgets;

use App\Models\ContactEvent;
use App\Models\Post;
use App\Support\DhakaTime;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** F23: posts and buyer contacts per Dhaka day. */
class DailyActivityChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'প্রতিদিনের কাজ';

    protected ?string $description = 'নতুন পোস্ট আর ক্রেতার যোগাযোগ (কল, মেসেজ, কমেন্ট, শেয়ার)';

    public ?string $filter = '14';

    protected ?string $maxHeight = '280px';

    protected function getFilters(): ?array
    {
        return ['7' => 'গত ৭ দিন', '14' => 'গত ১৪ দিন', '30' => 'গত ৩০ দিন'];
    }

    protected function getData(): array
    {
        // $filter comes from the browser: only accept our own keys.
        $days = match ($this->filter) {
            '7' => 7,
            '30' => 30,
            default => 14,
        };
        $from = DhakaTime::startOfDay($days - 1);

        $dates = collect(range($days - 1, 0))->map(fn ($d) => now(DhakaTime::TZ)->subDays($d)->toDateString());

        $perDay = fn (string $model) => $model::where('created_at', '>=', $from)
            ->selectRaw(DhakaTime::dateSql('created_at').' as day, COUNT(*) as n')
            ->groupBy('day')
            ->pluck('n', 'day');

        $posts = $perDay(Post::class);
        $contacts = $perDay(ContactEvent::class);

        return [
            'datasets' => [
                [
                    'label' => 'যোগাযোগ',
                    'data' => $dates->map(fn ($d) => (int) ($contacts[$d] ?? 0))->all(),
                    'borderColor' => '#1e5b3f',
                    'backgroundColor' => 'rgba(30, 91, 63, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'নতুন পোস্ট',
                    'data' => $dates->map(fn ($d) => (int) ($posts[$d] ?? 0))->all(),
                    'borderColor' => '#e8a90c',
                    'backgroundColor' => '#e8a90c',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $dates->map(fn ($d) => Carbon::parse($d)->format('j M'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
