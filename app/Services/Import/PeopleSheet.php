<?php

namespace App\Services\Import;

use League\Csv\Reader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * F59: reads an uploaded list (.xlsx or .csv) into rows keyed by our column
 * names. Headers may be in Bangla or English; unknown columns are ignored.
 */
class PeopleSheet
{
    public const MAX_ROWS = 2000;

    /** Our column => the header names people write. */
    public const HEADERS = [
        'phone' => ['মোবাইল', 'মোবাইল নম্বর', 'মোবাইল নাম্বার', 'ফোন', 'ফোন নম্বর', 'নম্বর', 'phone', 'mobile', 'phone number', 'mobile number', 'number'],
        'name' => ['নাম', 'name', 'full name'],
        'role' => ['ভূমিকা', 'ধরন', 'কী হিসেবে', 'role', 'type'],
        'area' => ['এলাকা', 'area', 'areas', 'zone'],
        'address' => ['ঠিকানা', 'address'],
        'kitchen_name' => ['রান্নাঘরের নাম', 'রান্নাঘর', 'kitchen', 'kitchen name', 'kitchen_name'],
        'location' => ['লোকেশন লিংক', 'লোকেশন', 'ম্যাপ', 'location', 'location link', 'map', 'map link'],
        'meals' => ['বেলা', 'meal', 'meals', 'meal_slots'],
        'plates' => ['প্লেট', 'plates', 'plate'],
        'days' => ['দিন', 'days', 'weekdays'],
        'campaign' => ['ক্যাম্পেইন', 'ক্যাম্পেন', 'বিজ্ঞাপন', 'campaign', 'source', 'ad'],
        'note' => ['নোট', 'মন্তব্য', 'note', 'notes', 'comment'],
    ];

    /**
     * @return list<array{line: int, values: array<string, string>}>
     *
     * @throws RuntimeException with a Bangla message the admin can act on
     */
    public function read(string $path, string $fileName): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $table = match ($ext) {
            'xlsx' => $this->xlsx($path),
            'csv', 'txt' => $this->csv($path),
            default => throw new RuntimeException('শুধু .xlsx বা .csv ফাইল দিন। পুরোনো .xls হলে এক্সেলে "Save As → .xlsx" করে নিন।'),
        };

        return $this->rows($table);
    }

    /** @return list<list<string>> */
    private function xlsx(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);
        try {
            $best = null;
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($cell) => self::text($cell->getValue()), $row->getCells());
                    if (count($rows) > self::MAX_ROWS + 50) {
                        break;
                    }
                }
                // The sheet whose first row has a phone column; "তালিকা" in our template.
                if ($rows && isset($this->map($rows[0])['phone'])) {
                    if ($sheet->getName() === 'তালিকা' || ! $best) {
                        $best = $rows;
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return $best ?? throw new RuntimeException('ফাইলে "মোবাইল" কলাম পাওয়া যায়নি। টেমপ্লেটের প্রথম লাইনের কলামের নামগুলো রাখুন।');
    }

    /** @return list<list<string>> */
    private function csv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // Excel's UTF-8 mark
        if (! mb_check_encoding($raw, 'UTF-8')) {
            throw new RuntimeException('CSV ফাইলটা UTF-8 নয়, বাংলা লেখা ভেঙে যাবে। এক্সেলে "CSV UTF-8" হিসেবে সেভ করুন, বা .xlsx দিন।');
        }
        $first = strtok($raw, "\n") ?: '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : (str_contains($first, "\t") && ! str_contains($first, ',') ? "\t" : ',');

        $reader = Reader::fromString($raw);
        $reader->setDelimiter($delimiter);

        $rows = [];
        foreach ($reader->getRecords() as $record) {
            $rows[] = array_map(fn ($v) => self::text($v), array_values($record));
            if (count($rows) > self::MAX_ROWS + 50) {
                break;
            }
        }

        return $rows;
    }

    /** @param  list<list<string>>  $table */
    private function rows(array $table): array
    {
        if (! $table) {
            throw new RuntimeException('ফাইলটা খালি।');
        }
        $columns = $this->map(array_shift($table));
        if (! isset($columns['phone'])) {
            throw new RuntimeException('ফাইলে "মোবাইল" কলাম পাওয়া যায়নি। টেমপ্লেটের প্রথম লাইনের কলামের নামগুলো রাখুন।');
        }

        $out = [];
        foreach ($table as $i => $cells) {
            $values = [];
            foreach ($columns as $key => $index) {
                $values[$key] = trim($cells[$index] ?? '');
            }
            if (implode('', $values) === '') {
                continue; // blank line
            }
            $out[] = ['line' => $i + 2, 'values' => $values];
        }
        if (count($out) > self::MAX_ROWS) {
            throw new RuntimeException('একবারে সর্বোচ্চ '.self::MAX_ROWS.' জন। ফাইলটা ভাগ করে আপলোড করুন।');
        }

        return $out;
    }

    /** @return array<string, int> our column => position */
    private function map(array $header): array
    {
        $lookup = [];
        foreach (self::HEADERS as $key => $names) {
            foreach ($names as $name) {
                $lookup[self::key($name)] = $key;
            }
        }
        $columns = [];
        foreach ($header as $index => $title) {
            $key = $lookup[self::key((string) $title)] ?? null;
            if ($key && ! isset($columns[$key])) {
                $columns[$key] = $index;
            }
        }

        return $columns;
    }

    private static function key(string $title): string
    {
        $title = preg_replace('/\s*\(.*\)\s*|\*/u', '', $title); // "মোবাইল (আবশ্যক)*" → "মোবাইল"

        return mb_strtolower(trim(preg_replace('/[\s_]+/u', ' ', $title)));
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            is_float($value) && floor($value) === $value => number_format($value, 0, '.', ''),
            default => trim((string) $value),
        };
    }
}
