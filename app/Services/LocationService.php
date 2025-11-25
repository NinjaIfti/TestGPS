<?php

namespace App\Services;

use App\Models\LocationUpdate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LocationService
{
    public function __construct(
        private readonly LocationRedisService $redisService,
    ) {
    }

    /**
     * Persist the latest location for a user in Redis.
     */
    public function storeLocation(int $userId, array $locationData): array
    {
        $payload = $this->prepareRedisPayload($locationData);

        $this->redisService->storeLocation($userId, $payload);

        return $this->formatPayloadForResponse($userId, $payload);
    }

    /**
     * Retrieve a user's location from Redis with MySQL fallback.
     */
    public function getLocation(int $userId): ?array
    {
        $location = $this->redisService->getLocation($userId);

        if ($location) {
            return $location;
        }

        $record = LocationUpdate::query()->where('user_id', $userId)->first();

        return $record ? $this->formatModelLocation($record) : null;
    }

    /**
     * Fetch all active locations, defaulting to Redis.
     */
    public function getAllActiveLocations(int $limit = 10000): array
    {
        $locations = $this->redisService->getAllActiveLocations($limit);

        if (!empty($locations)) {
            return array_values($locations);
        }

        return LocationUpdate::query()
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (LocationUpdate $location) => $this->formatModelLocation($location))
            ->all();
    }

    /**
     * Number of users that have not expired in Redis.
     */
    public function getActiveUserCount(): int
    {
        return $this->redisService->getActiveUserCount();
    }

    /**
     * Remove expired users from Redis.
     */
    public function cleanupInactiveUsers(): int
    {
        return $this->redisService->cleanupExpiredUsers();
    }

    /**
     * Batch of locations queued for persistence.
     */
    public function getLocationsForSync(int $batchSize = 1000): array
    {
        return array_values($this->redisService->getUsersForSync($batchSize));
    }

    /**
     * Delete a user's cached location.
     */
    public function deleteLocation(int $userId): bool
    {
        return $this->redisService->deleteLocation($userId);
    }

    /**
     * Persist latest Redis locations into MySQL and keep Redis tidy.
     */
    public function syncLocationsToDatabase(int $batchSize = null): array
    {
        $batchSize = $batchSize ?? config('gps.max_batch_size', 1000);
        $locations = $this->redisService->getUsersForSync($batchSize);

        if (empty($locations)) {
            return [
                'processed' => 0,
                'synced' => 0,
                'errors' => 0,
                'cleaned' => $this->cleanupInactiveUsers(),
            ];
        }

        $synced = 0;
        $errors = 0;

        DB::transaction(function () use (&$synced, &$errors, $locations) {
            foreach ($locations as $userId => $location) {
                try {
                    LocationUpdate::updateOrCreate(
                        ['user_id' => $userId],
                        [
                            'latitude' => $location['latitude'],
                            'longitude' => $location['longitude'],
                            'altitude' => $location['altitude'],
                            'accuracy' => $location['accuracy'],
                            'speed' => $location['speed'],
                            'heading' => $location['heading'],
                            'recorded_at' => $location['recorded_at']
                                ? Carbon::parse($location['recorded_at'])
                                : now(),
                        ],
                    );

                    $synced++;
                } catch (\Throwable $throwable) {
                    $errors++;
                    Log::error('Failed to persist user location', [
                        'user_id' => $userId,
                        'error' => $throwable->getMessage(),
                    ]);
                }
            }
        });

        $this->redisService->markAsSynced(array_keys($locations));
        $cleaned = $this->cleanupInactiveUsers();

        return [
            'processed' => count($locations),
            'synced' => $synced,
            'errors' => $errors,
            'cleaned' => $cleaned,
        ];
    }

    /**
     * Aggregate runtime stats for dashboards.
     */
    public function getSystemStats(): array
    {
        $redisStats = $this->redisService->getStats();

        return [
            'active_users' => $this->getActiveUserCount(),
            'pending_sync' => $redisStats['pending_sync'],
            'memory_used' => $redisStats['memory_used'],
            'config' => [
                'location_ttl' => config('gps.location_ttl'),
                'sync_interval' => config('gps.sync_interval'),
                'max_batch_size' => config('gps.max_batch_size'),
            ],
        ];
    }

    /**
     * Ensure Redis payload uses numeric types and timestamps.
     */
    private function prepareRedisPayload(array $locationData): array
    {
        $recordedAt = $locationData['recorded_at'] ?? null;
        $recordedTimestamp = $recordedAt
            ? Carbon::parse($recordedAt)->timestamp
            : now()->timestamp;

        return [
            'latitude' => (float) $locationData['latitude'],
            'longitude' => (float) $locationData['longitude'],
            'altitude' => isset($locationData['altitude']) ? (float) $locationData['altitude'] : null,
            'accuracy' => isset($locationData['accuracy']) ? (float) $locationData['accuracy'] : null,
            'speed' => isset($locationData['speed']) ? (float) $locationData['speed'] : null,
            'heading' => isset($locationData['heading']) ? (float) $locationData['heading'] : null,
            'recorded_at' => $recordedTimestamp,
        ];
    }

    /**
     * Convert Redis payload into API-friendly format.
     */
    private function formatPayloadForResponse(int $userId, array $payload): array
    {
        return [
            'user_id' => $userId,
            'latitude' => $payload['latitude'],
            'longitude' => $payload['longitude'],
            'altitude' => $payload['altitude'],
            'accuracy' => $payload['accuracy'],
            'speed' => $payload['speed'],
            'heading' => $payload['heading'],
            'recorded_at' => Carbon::createFromTimestamp($payload['recorded_at'])->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Normalize database model output to match API schema.
     */
    private function formatModelLocation(LocationUpdate $location): array
    {
        return [
            'user_id' => $location->user_id,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'altitude' => $location->altitude ? (float) $location->altitude : null,
            'accuracy' => $location->accuracy ? (float) $location->accuracy : null,
            'speed' => $location->speed ? (float) $location->speed : null,
            'heading' => $location->heading ? (float) $location->heading : null,
            'recorded_at' => $location->recorded_at?->toIso8601String(),
            'updated_at' => $location->updated_at?->toIso8601String(),
        ];
    }
}
