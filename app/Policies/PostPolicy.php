<?php

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    /** A cook may change plates or mark sold out only on their own post, while it can still sell. */
    public function manage(User $user, Post $post): Response
    {
        if ($post->kitchen->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return match ($post->status) {
            PostStatus::Hidden => Response::deny('দাওয়াত টিম এই পোস্টটি বন্ধ করেছে। প্রশ্ন থাকলে যোগাযোগ করুন।'),
            PostStatus::Expired => Response::deny('এই পোস্টের সময় শেষ। নতুন পোস্ট দিন।'),
            default => $post->available_until->isPast()
                ? Response::deny('এই পোস্টের সময় শেষ। নতুন পোস্ট দিন।')
                : Response::allow(),
        };
    }
}
