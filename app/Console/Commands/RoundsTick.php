<?php

namespace App\Console\Commands;

use App\Services\Regular\OfficeRounds;
use Illuminate\Console\Command;

/** F95: cooks whose turn on an office round is within 30 minutes are told. */
class RoundsTick extends Command
{
    protected $signature = 'rounds:tick';

    protected $description = 'Tell cooks the area manager is coming soon on today\'s office rounds';

    public function handle(OfficeRounds $rounds): int
    {
        $this->info($rounds->tick().' cooks told');

        return self::SUCCESS;
    }
}
