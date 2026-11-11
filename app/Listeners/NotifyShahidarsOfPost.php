<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\PostPublished;
use App\Models\Order;
use App\Services\Push\Push;

/** F44: a cook posted (or was posted for): each shahidar learns how many of their boxes it booked. */
class NotifyShahidarsOfPost
{
    public function handle(PostPublished $event): void
    {
        $post = $event->post;
        $orders = Order::where('post_id', $post->id)->whereNotNull('shahidar_id')->whereNotNull('customer_id')
            ->where('status', '!=', OrderStatus::Cancelled)->get();

        foreach ($orders->groupBy('shahidar_id') as $shahidarId => $mine) {
            $booked = $mine->where('status', '!=', OrderStatus::Asking)->count();
            $asking = $mine->where('status', OrderStatus::Asking)->count();
            $body = collect([
                $booked ? 'আপনার '.Order::banglaDigits((string) $booked).'টা বক্স বুক হয়েছে' : null,
                $asking ? Order::banglaDigits((string) $asking).' জনকে দাম জিজ্ঞেস করুন' : null,
            ])->filter()->implode(', ');

            Push::toUser((int) $shahidarId, "{$post->kitchen->display_name}: আজ {$post->meal_slot->label()} পোস্ট হয়েছে",
                $body.' · '.$post->title, '/shahidar', 'post-'.$post->id.'-'.$shahidarId);
        }
    }
}
