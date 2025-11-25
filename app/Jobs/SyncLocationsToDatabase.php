<?php

namespace App\Jobs;

use App\Services\LocationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLocationsToDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     *
     * @var int
     */
    public $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * Syncs location data from Redis to MySQL.
     */
    public function handle(LocationService $locationService): void
    {
        $batchSize = config('gps.max_batch_size', 1000);
        $result = $locationService->syncLocationsToDatabase($batchSize);

        if ($result['processed'] === 0) {
            Log::info('No locations pending sync');
            return;
        }

        Log::info('Location sync completed', $result);
    }
}
