<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Hook for later: share card (F19), notifications, telling the area's shahidar. */
class PostPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Post $post) {}
}
