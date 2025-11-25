<?php

namespace App\Jobs;

use App\Models\UserLocation;
use App\Services\LocationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
        $batchSize = config('app.gps_max_batch_size', 1000);
        $locations = $locationService->getLocationsForSync($batchSize);

        if (empty($locations)) {
            Log::info('No locations to sync');
            return;
        }

        $syncedCount = 0;
        $errorCount = 0;

        DB::beginTransaction();

        try {
            foreach ($locations as $locationData) {
                try {
                    // Use updateOrCreate for upsert operation
                    UserLocation::updateOrCreate(
                        ['user_id' => $locationData['user_id']],
                        [
                            'latitude' => $locationData['latitude'],
                            'longitude' => $locationData['longitude'],
                            'altitude' => $locationData['altitude'] ?? null,
                            'accuracy' => $locationData['accuracy'] ?? null,
                            'speed' => $locationData['speed'] ?? null,
                            'heading' => $locationData['heading'] ?? null,
                            'recorded_at' => $locationData['recorded_at'] ?? now(),
                        ]
                    );

                    $syncedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to sync location for user ' . ($locationData['user_id'] ?? 'unknown'), [
                        'error' => $e->getMessage(),
                        'location_data' => $locationData,
                    ]);
                }
            }

            DB::commit();

            Log::info('Location sync completed', [
                'synced' => $syncedCount,
                'errors' => $errorCount,
                'total_processed' => count($locations),
            ]);

            // Clean up inactive users from Redis
            $cleanedCount = $locationService->cleanupInactiveUsers();
            Log::info('Cleaned up inactive users', ['count' => $cleanedCount]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Location sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
