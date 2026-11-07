<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContactType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\ContactRequest;
use App\Models\ContactEvent;
use App\Models\Post;
use App\Models\QrCode;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    /**
     * POST /api/v1/posts/{post}/contact {type: call|message|share, qr?}
     *
     * F16: the number is only handed out when a buyer actually taps call or
     * message, and only if the cook allowed that button.
     * F18: every tap is logged — the MVP's measure of demand.
     */
    public function store(ContactRequest $request, Post $post): JsonResponse
    {
        $type = ContactType::from($request->validated('type'));
        $kitchen = $post->kitchen;

        // Numbers go only to signed-in buyers, so they can't be harvested anonymously.
        if (in_array($type, [ContactType::Call, ContactType::Message], true) && ! auth('sanctum')->check()) {
            return response()->json(['message' => 'কল বা মেসেজ করতে মোবাইল নম্বর দিয়ে একবার ঢুকুন।'], 401);
        }

        if (! Post::visibleNow()->whereKey($post->id)->exists() || ! $kitchen->isApproved() || $kitchen->user->isBlocked()) {
            return response()->json(['message' => 'এই খাবারটি আর পাওয়া যাচ্ছে না।'], 410);
        }

        $allowed = match ($type) {
            ContactType::Call => $kitchen->allow_call,
            ContactType::Message => $kitchen->allow_message,
            default => true,
        };
        if (! $allowed) {
            return response()->json(['message' => 'রাঁধুনি এভাবে যোগাযোগ বন্ধ রেখেছেন।'], 403);
        }

        $qrId = $request->filled('qr')
            ? QrCode::where('code', strtolower($request->validated('qr')))->value('id')
            : null;

        ContactEvent::create([
            'post_id' => $post->id,
            'viewer_id' => auth('sanctum')->id(),
            'qr_code_id' => $qrId,
            'type' => $type,
        ]);

        $phone = $kitchen->publicContactPhone();

        return response()->json(['data' => match ($type) {
            ContactType::Call => [
                'tel' => 'tel:+'.PhoneNumber::international($phone),
                'name' => $kitchen->contact_name ?: $kitchen->display_name,
            ],
            ContactType::Message => [
                'whatsapp_url' => 'https://wa.me/'.PhoneNumber::international($phone).'?text='.rawurlencode($this->message($post)),
                'name' => $kitchen->contact_name ?: $kitchen->display_name,
            ],
            default => ['share_url' => url('/p/'.$post->id)],
        }]);
    }

    /** Pre-filled WhatsApp text, so the cook knows which post it is about. */
    private function message(Post $post): string
    {
        return "আসসালামু আলাইকুম। দাওয়াত অ্যাপে আপনার \"{$post->title}\" দেখলাম। "
            .'এক প্লেট পাওয়া যাবে? '.url('/p/'.$post->id);
    }
}
