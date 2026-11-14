<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F59: one line of an uploaded list and what happened to it. */
class PeopleImportRow extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public const ROLES = ['customer' => 'গ্রাহক', 'cook' => 'গৃহিণী', 'shahidar' => 'শাহিদার'];

    public const PLANS = ['new' => 'নতুন যোগ হবে', 'add_role' => 'আগে থেকে আছেন, ভূমিকা যোগ হবে', 'exists' => 'আগে থেকেই আছেন, বাদ', 'error' => 'ভুল আছে'];

    public const RESULTS = ['added' => 'যোগ হয়েছে', 'role_added' => 'ভূমিকা যোগ হয়েছে', 'skipped' => 'বাদ', 'failed' => 'হয়নি'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PeopleImport::class, 'people_import_id');
    }
}
