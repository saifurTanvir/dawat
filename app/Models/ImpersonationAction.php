<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** F51: one change made while someone used the app as another person. */
class ImpersonationAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** A plain Bangla name for the change, from the API path. */
    public function label(): string
    {
        $p = $this->path;

        return match (true) {
            str_contains($p, '/posts') && $this->method === 'POST' && ! str_contains($p, 'sold-out') && ! str_contains($p, 'orders') => 'রান্না পোস্ট',
            str_contains($p, 'sold-out') => 'পোস্ট "শেষ"',
            str_contains($p, 'my/posts') && $this->method === 'PATCH' => 'প্লেট বদল',
            str_contains($p, 'bookings/ready') => 'খাবার রেডি জানানো',
            str_contains($p, 'bookings/off') => '"আজ রান্না নেই"',
            str_contains($p, 'my/menus') => 'প্রতিদিনের মেনু বদল',
            str_contains($p, '/decline') => 'অর্ডার "দিতে পারছি না"',
            str_contains($p, '/handed') => 'ক্রেতাকে খাবার দেওয়া',
            str_contains($p, 'reviews') && str_contains($p, 'reply') => 'রিভিউর উত্তর',
            str_contains($p, 'kitchen') => 'রান্নাঘরের তথ্য বদল',
            str_contains($p, 'regular/orders') && str_contains($p, 'skip') => '"আজ লাগবে না"',
            str_contains($p, 'regular/orders') && str_contains($p, 'accept') => 'বেশি দামে "নেব"',
            str_contains($p, 'regular/pauses') || str_contains($p, 'regular/pause') => 'বিরতি',
            str_contains($p, 'regular') => 'নিয়মিত বুকিং বদল',
            str_contains($p, '/orders') && str_contains($p, 'cancel') => 'অর্ডার বাতিল',
            str_contains($p, '/orders') => 'অর্ডার',
            str_contains($p, 'history') && str_contains($p, 'review') => 'রিভিউ দেওয়া',
            str_contains($p, 'contact-hours') => 'কখন ফোন করা যাবে',
            str_contains($p, '/me') => 'নাম বদল',
            str_contains($p, 'comments') => 'মন্তব্য',
            default => $this->method.' '.$p,
        };
    }
}
