@include('filament.history.summary', ['stats' => app(\App\Services\Admin\History::class)->kitchen($getRecord())])
