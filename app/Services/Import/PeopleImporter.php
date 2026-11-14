<?php

namespace App\Services\Import;

use App\Enums\CustomerStatus;
use App\Filament\Actions\QuickAddActions;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Shahidars\ShahidarResource;
use App\Models\Area;
use App\Models\Customer;
use App\Models\PeopleImport;
use App\Models\PeopleImportRow;
use App\Models\User;
use App\Support\Geo\GeoPoint;
use App\Support\Geo\MapLink;
use App\Support\PhoneNumber;
use App\Support\Roles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * F59: from an uploaded list to customers, cooks and shahidars.
 *
 * preview() checks every line and says what will happen; nothing is created.
 * run() then adds them, line by line, so one bad line never stops the rest.
 * Nobody gets an SMS. Existing people keep their name and details; they only
 * gain the new role. Cooks start "pending" — an admin approves after a call.
 */
class PeopleImporter
{
    private const ROLE_WORDS = [
        'customer' => ['গ্রাহক', 'কাস্টমার', 'ক্রেতা', 'customer', 'buyer', 'client'],
        'cook' => ['গৃহিণী', 'গৃহিনী', 'রাঁধুনি', 'রাধুনি', 'রাঁধুনী', 'cook', 'kitchen', 'chef', 'home cook'],
        'shahidar' => ['শাহিদার', 'সাহিদার', 'shahidar', 'rider', 'delivery'],
    ];

    private const MEAL_WORDS = [
        'breakfast' => ['সকাল', 'সকালের নাস্তা', 'নাস্তা', 'breakfast', 'morning'],
        'lunch' => ['দুপুর', 'দুপুরের খাবার', 'লাঞ্চ', 'lunch', 'noon'],
        'snack' => ['বিকেল', 'বিকাল', 'বিকেলের নাস্তা', 'snack', 'evening'],
        'dinner' => ['রাত', 'রাতের খাবার', 'ডিনার', 'dinner', 'night'],
    ];

    private const DAY_WORDS = [
        0 => ['রবি', 'রবিবার', 'sun', 'sunday'],
        1 => ['সোম', 'সোমবার', 'mon', 'monday'],
        2 => ['মঙ্গল', 'মঙ্গলবার', 'tue', 'tuesday'],
        3 => ['বুধ', 'বুধবার', 'wed', 'wednesday'],
        4 => ['বৃহস্পতি', 'বৃহস্পতিবার', 'বিষ্যুদ', 'thu', 'thursday'],
        5 => ['শুক্র', 'শুক্রবার', 'fri', 'friday'],
        6 => ['শনি', 'শনিবার', 'sat', 'saturday'],
    ];

    /** @var Collection<string, Area>|null name/slug (normalised) => area */
    private ?Collection $areas = null;

    public function __construct(private readonly PeopleSheet $sheet) {}

    /** Reads the file and stores a preview. Throws RuntimeException for a file that cannot be read. */
    public function preview(string $path, string $fileName, User $admin): PeopleImport
    {
        $rows = $this->sheet->read($path, $fileName);

        return DB::transaction(function () use ($rows, $fileName, $admin) {
            $import = PeopleImport::create(['file_name' => mb_substr($fileName, 0, 200), 'uploaded_by' => $admin->id, 'total' => count($rows), 'status' => 'preview']);
            $seen = [];
            foreach ($rows as $row) {
                $import->rows()->create($this->plan($row['line'], $row['values'], $seen));
            }

            return $import;
        });
    }

    /** What would happen to one line. $seen tracks numbers already in the file. */
    public function plan(int $line, array $v, array &$seen): array
    {
        $errors = [];
        $phone = self::phone($v['phone'] ?? '');
        $role = $this->role($v['role'] ?? '');
        $name = trim(preg_replace('/\s+/u', ' ', $v['name'] ?? ''));
        $data = ['campaign' => self::clip($v['campaign'] ?? '', 80), 'note' => self::clip($v['note'] ?? '', 300)];

        if (! $phone) {
            $errors[] = filled($v['phone'] ?? '') ? 'মোবাইল নম্বর ঠিক নেই' : 'মোবাইল নম্বর নেই';
        } elseif (isset($seen[$phone])) {
            $errors[] = "একই নম্বর ফাইলে আগে আছে (লাইন {$seen[$phone]})";
        }
        if (mb_strlen($name) < 2) {
            $errors[] = 'নাম নেই';
        }
        if (! $role) {
            $errors[] = filled($v['role'] ?? '') ? 'ভূমিকা বোঝা যায়নি (গ্রাহক / গৃহিণী / শাহিদার)' : 'ভূমিকা নেই';
        }

        $areaIds = [];
        foreach (preg_split('/[,،;|\/]+/u', $v['area'] ?? '') as $part) {
            if (trim($part) === '') {
                continue;
            }
            $area = $this->area($part);
            $area ? $areaIds[] = $area->id : $errors[] = 'এলাকা মেলেনি: "'.trim($part).'"';
        }
        if (! $areaIds && ! collect($errors)->contains(fn ($e) => str_starts_with($e, 'এলাকা'))) {
            $errors[] = 'এলাকা নেই';
        }
        if ($role !== 'shahidar' && count($areaIds) > 1) {
            $errors[] = 'গ্রাহক বা গৃহিণীর একটাই এলাকা হয়';
        }
        $data['area_ids'] = array_values(array_unique($areaIds));

        $notes = [];
        $location = null;
        if (filled($v['location'] ?? '') && in_array($role, ['customer', 'cook'], true)) {
            $point = MapLink::parse($v['location']);
            $point ? $location = [$point->lat, $point->lng] : $notes[] = 'লোকেশন পড়া যায়নি, বাদ দেওয়া হবে';
        }
        $data['location'] = $location;

        if ($role === 'customer') {
            $data['address'] = self::clip($v['address'] ?? '', 255);
            if (mb_strlen($data['address']) < 5) {
                $errors[] = 'গ্রাহকের ডেলিভারির ঠিকানা নেই';
            }
            [$data['meal_slots'], $bad] = self::pick($v['meals'] ?? '', self::MEAL_WORDS, ['lunch']);
            if ($bad) {
                $errors[] = 'বেলা বোঝা যায়নি: '.implode(', ', $bad);
            }
            [$data['weekdays'], $bad] = self::pick(preg_replace('/প্রতিদিন|রোজ|সব দিন|every ?day|daily/iu', '', $v['days'] ?? ''), self::DAY_WORDS, Customer::ALL_WEEKDAYS);
            if ($bad) {
                $errors[] = 'দিন বোঝা যায়নি: '.implode(', ', $bad);
            }
            $plates = filled($v['plates'] ?? '') ? (int) self::digits($v['plates']) : 1;
            if ($plates < 1 || $plates > 10) {
                $errors[] = 'প্লেট ১ থেকে ১০';
            }
            $data['plates'] = max(1, min(10, $plates));
        } elseif ($role === 'cook') {
            $data['address'] = self::clip($v['address'] ?? '', 255);
            $data['kitchen_name'] = self::clip($v['kitchen_name'] ?? '', 60) ?: ($name ? self::clip($name.'-এর রান্নাঘর', 60) : '');
        }

        $plan = 'new';
        $user = $phone ? User::where('phone', $phone)->first() : null;
        if ($user && ! $errors) {
            if ($user->hasRole(Roles::ADMIN)) {
                $errors[] = 'এটা অ্যাডমিন অ্যাকাউন্ট';
            } elseif (self::hasRole($user, $role)) {
                $plan = 'exists';
                $notes[] = 'আগে থেকেই '.PeopleImportRow::ROLES[$role];
            } else {
                $plan = 'add_role';
                $notes[] = 'আগে থেকে অ্যাকাউন্ট আছে ('.($user->name ?: 'নাম নেই').'), নাম-ঠিকানা বদলাবে না';
            }
        }
        if ($phone && ! isset($seen[$phone])) {
            $seen[$phone] = $line;
        }

        return [
            'line' => $line,
            'phone' => $phone ?: mb_substr($v['phone'] ?? '', 0, 20),
            'name' => mb_substr($name, 0, 120),
            'role' => $role,
            'data' => $data,
            'plan' => $errors ? 'error' : $plan,
            'message' => mb_substr(implode('; ', [...$errors, ...$notes]), 0, 300) ?: null,
            'user_id' => $user?->id,
        ];
    }

    /** Adds everyone the preview said could be added. */
    public function run(PeopleImport $import, User $admin): PeopleImport
    {
        abort_unless($import->status === 'preview', 409, 'এই তালিকা আগেই যোগ করা হয়েছে।');
        $import->forceFill(['status' => 'running'])->save();

        foreach ($import->rows()->get() as $row) {
            if (in_array($row->plan, ['error', 'exists'], true)) {
                $row->forceFill(['result' => $row->plan === 'exists' ? 'skipped' : 'failed'])->save();

                continue;
            }
            try {
                $user = DB::transaction(fn () => $this->add($row, $import));
                $row->forceFill(['result' => $row->plan === 'add_role' ? 'role_added' : 'added', 'user_id' => $user->id])->save();
            } catch (Throwable $e) {
                $message = $e instanceof ValidationException ? collect($e->errors())->flatten()->implode(' ') : 'হয়নি: '.$e->getMessage();
                $row->forceFill(['result' => 'failed', 'message' => mb_substr($message, 0, 300)])->save();
                report($e);
            }
        }

        $counts = $import->rows()->reorder()->selectRaw('result, COUNT(*) AS n')->groupBy('result')->pluck('n', 'result');
        $import->forceFill([
            'status' => 'done',
            'added' => (int) ($counts['added'] ?? 0),
            'role_added' => (int) ($counts['role_added'] ?? 0),
            'skipped' => (int) ($counts['skipped'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'completed_at' => now(),
        ])->save();

        return $import;
    }

    private function add(PeopleImportRow $row, PeopleImport $import): User
    {
        $d = $row->data;
        $point = $d['location'] ? GeoPoint::make($d['location'][0], $d['location'][1]) : null;

        $user = match ($row->role) {
            'customer' => (function () use ($row, $d, $point) {
                $customer = CustomerResource::createCustomer([
                    'phone' => $row->phone, 'name' => $row->name, 'area_id' => $d['area_ids'][0], 'address' => $d['address'],
                    'note' => $d['note'] ?: null, 'meal_slots' => $d['meal_slots'], 'plates' => $d['plates'], 'weekdays' => $d['weekdays'],
                ]);
                $customer->forceFill(['source' => 'import', 'status' => $customer->pairing ? $customer->status : CustomerStatus::Waiting]);
                if ($point && ! $customer->location) {
                    $customer->location = $point;
                }
                $customer->save();

                return $customer->user;
            })(),
            'cook' => QuickAddActions::createCook([
                'phone' => $row->phone, 'name' => $row->name, 'display_name' => $d['kitchen_name'], 'area_id' => $d['area_ids'][0],
                'pickup_address' => $d['address'] ?: null, 'where' => $point ? sprintf('%.6f,%.6f', $point->lat, $point->lng) : null, 'approve' => false,
            ])->user,
            'shahidar' => ShahidarResource::createShahidar(['phone' => $row->phone, 'name' => $row->name, 'area_ids' => $d['area_ids']]),
        };

        // Where they came from, without overwriting anything already known.
        $user->forceFill([
            'campaign' => $user->campaign ?: ($d['campaign'] ?: null),
            'intake_note' => $user->intake_note ?: ($d['note'] ?: null),
            'people_import_id' => $user->people_import_id ?: $import->id,
        ])->save();

        return $user;
    }

    public static function hasRole(User $user, ?string $role): bool
    {
        return match ($role) {
            'customer' => $user->customer()->where('status', '!=', CustomerStatus::Stopped)->exists(),
            'cook' => $user->kitchen()->exists(),
            'shahidar' => $user->isShahidar(),
            default => false,
        };
    }

    private function role(string $text): ?string
    {
        $t = mb_strtolower(trim($text));
        foreach (self::ROLE_WORDS as $role => $words) {
            if (in_array($t, $words, true)) {
                return $role;
            }
        }

        return null;
    }

    private function area(string $text): ?Area
    {
        $this->areas ??= Area::active()->get()->flatMap(fn (Area $a) => [self::norm($a->name) => $a, self::norm($a->slug) => $a]);

        return $this->areas[self::norm($text)] ?? null;
    }

    /** "০১৭১১-০০০০০১", "+880 1711 000001", 1711000001 (Excel dropped the 0) → 01711000001 */
    public static function phone(string $text): ?string
    {
        $digits = preg_replace('/\D+/', '', self::digits($text));
        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '0'.$digits;
        }

        return PhoneNumber::normalize($digits);
    }

    /** Words separated by commas → their keys; unknown words are returned too. */
    private static function pick(string $text, array $words, array $default): array
    {
        $parts = array_filter(array_map('trim', preg_split('/[,،;|\/]+|\s+ও\s+|\s+and\s+/u', $text)), 'strlen');
        if (! $parts) {
            return [$default, []];
        }
        $keys = [];
        $bad = [];
        foreach ($parts as $part) {
            $p = mb_strtolower($part);
            $key = collect($words)->search(fn ($list) => in_array($p, $list, true));
            $key === false ? $bad[] = $part : $keys[] = $key;
        }

        return [array_values(array_unique($keys)) ?: $default, $bad];
    }

    private static function norm(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim(self::digits($text))));
    }

    private static function digits(string $text): string
    {
        return strtr($text, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }

    private static function clip(string $text, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, $max);
    }
}
