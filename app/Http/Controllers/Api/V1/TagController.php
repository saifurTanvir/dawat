<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    /** GET /api/v1/tags — the tap-to-select tags, in display order. */
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::active()->get());
    }
}
