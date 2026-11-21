<?php

namespace App\Http\Controllers\Web;

use App\Filament\Resources\MessengerThreads\MessengerThreadResource;
use App\Http\Controllers\Controller;
use App\Services\Messenger\Graph;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * F85: reconnect the page with Facebook Login. The admin approves on Facebook;
 * the server swaps the code for a long-lived user token and then the page's own
 * token, and saves it encrypted — no one copies or pastes a token.
 */
class MessengerConnectController extends Controller
{
    public const SCOPES = ['pages_messaging', 'pages_manage_metadata', 'pages_read_engagement', 'pages_show_list'];

    public function start(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $appId = (string) config('services.messenger.app_id');
        abort_if($appId === '', 503, 'MESSENGER_APP_ID নেই');

        $state = Str::random(40);
        $request->session()->put('messenger_connect_state', $state);

        return redirect()->away('https://www.facebook.com/'.self::version().'/dialog/oauth?'.http_build_query([
            'client_id' => $appId,
            'redirect_uri' => self::redirectUri(),
            'state' => $state,
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $back = redirect(MessengerThreadResource::getUrl());
        $state = $request->session()->pull('messenger_connect_state');
        $fail = function (string $why) use ($back) {
            Notification::make()->title('পেজ যুক্ত হয়নি')->body($why)->danger()->persistent()->send();

            return $back;
        };

        if (! $state || ! hash_equals($state, (string) $request->query('state'))) {
            return $fail('লিংকটা পুরনো বা ভুল — আবার বাটন চাপুন।');
        }
        if ($request->filled('error')) {
            return $fail('Facebook-এ অনুমতি দেওয়া হয়নি ('.Str::limit((string) $request->query('error_description', $request->query('error')), 120).')');
        }

        $graph = (string) config('services.messenger.graph');
        $app = ['client_id' => config('services.messenger.app_id'), 'client_secret' => config('services.messenger.app_secret')];
        try {
            $short = Http::timeout(15)->get($graph.'/oauth/access_token', $app + [
                'redirect_uri' => self::redirectUri(), 'code' => (string) $request->query('code'),
            ]);
            if (! $short->json('access_token')) {
                return $fail('Meta: '.($short->json('error.message') ?: 'কোড বদলানো যায়নি'));
            }
            // Long-lived user token → the page token taken from it never expires (until a password change).
            $long = Http::timeout(15)->get($graph.'/oauth/access_token', $app + [
                'grant_type' => 'fb_exchange_token', 'fb_exchange_token' => $short->json('access_token'),
            ]);
        } catch (ConnectionException) {
            return $fail('Meta-র সার্ভারে পৌঁছানো যায়নি — একটু পরে আবার চেষ্টা করুন।');
        }

        $check = Graph::checkToken((string) ($long->json('access_token') ?: $short->json('access_token')));
        if (! $check['ok']) {
            return $fail($check['error']);
        }
        Graph::savePageToken($check['token'], $request->user());
        Graph::clearTokenProblem();
        Notification::make()->title('পেজ আবার যুক্ত হলো — '.$check['name'])->body('এখন "অপেক্ষার চ্যাটে উত্তর দিন" চাপুন।')->success()->send();

        return $back;
    }

    public static function redirectUri(): string
    {
        return route('admin.messenger.connect.callback');
    }

    private static function version(): string
    {
        return preg_match('~/(v\d+\.\d+)~', (string) config('services.messenger.graph'), $m) ? $m[1] : 'v21.0';
    }
}
