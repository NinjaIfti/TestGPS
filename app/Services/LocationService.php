<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLocation;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class LocationService
{
    /**
     * Redis key prefix for user locations.
     */
    private const LOCATION_PREFIX = 'location:user:';

    /**
     * Redis key for active users set.
     */
    private const ACTIVE_USERS_KEY = 'active_users';

    /**
     * Store or update user location in Redis.
     *
     * @param int $userId
     * @param array $locationData
     * @return array
     */
    public function storeLocation(int $userId, array $locationData): array
    {
        $key = $this->getLocationKey($userId);
        $ttl = config('app.gps_location_ttl', 3600);

        // Add timestamp if not present
        $locationData['updated_at'] = $locationData['updated_at'] ?? now()->toIso8601String();
        $locationData['user_id'] = $userId;

        // Store location data in Redis as hash
        Redis::hmset($key, $locationData);
        Redis::expire($key, $ttl);

        // Add user to active users set with score as timestamp
        Redis::zadd(self::ACTIVE_USERS_KEY, now()->timestamp, $userId);

        return $locationData;
    }

    /**
     * Get user location from Redis.
     *
     * @param int $userId
     * @return array|null
     */
    public function getLocation(int $userId): ?array
    {
        $key = $this->getLocationKey($userId);
        $location = Redis::hgetall($key);

        return !empty($location) ? $location : null;
    }

    /**
     * Get all active users' locations from Redis.
     *
     * @param int $limit
     * @return array
     */
    public function getAllActiveLocations(int $limit = 10000): array
    {
        // Get active user IDs from sorted set
        $userIds = Redis::zrevrange(self::ACTIVE_USERS_KEY, 0, $limit - 1);

        if (empty($userIds)) {
            return [];
        }

        $locations = [];

        // Use pipeline for better performance
        $results = Redis::pipeline(function ($pipe) use ($userIds) {
            foreach ($userIds as $userId) {
                $pipe->hgetall($this->getLocationKey($userId));
            }
        });

        foreach ($results as $index => $location) {
            if (!empty($location)) {
                $locations[] = $location;
            }
        }

        return $locations;
    }

    /**
     * Get count of active users.
     *
     * @return int
     */
    public function getActiveUserCount(): int
    {
        return Redis::zcard(self::ACTIVE_USERS_KEY);
    }

    /**
     * Remove inactive users from active set.
     * Users are considered inactive if their last update was more than TTL seconds ago.
     *
     * @return int Number of users removed
     */
    public function cleanupInactiveUsers(): int
    {
        $ttl = config('app.gps_location_ttl', 3600);
        $cutoffTime = now()->subSeconds($ttl)->timestamp;

        return Redis::zremrangebyscore(self::ACTIVE_USERS_KEY, '-inf', $cutoffTime);
    }

    /**
     * Get batch of locations for MySQL sync.
     *
     * @param int $batchSize
     * @return array
     */
    public function getLocationsForSync(int $batchSize = 1000): array
    {
        $userIds = Redis::zrevrange(self::ACTIVE_USERS_KEY, 0, $batchSize - 1);

        if (empty($userIds)) {
            return [];
        }

        $locations = [];

        foreach ($userIds as $userId) {
            $location = $this->getLocation($userId);
            if ($location) {
                $locations[] = $location;
            }
        }

        return $locations;
    }

    /**
     * Delete user location from Redis.
     *
     * @param int $userId
     * @return bool
     */
    public function deleteLocation(int $userId): bool
    {
        $key = $this->getLocationKey($userId);

        Redis::del($key);
        Redis::zrem(self::ACTIVE_USERS_KEY, $userId);

        return true;
    }

    /**
     * Get Redis key for user location.
     *
     * @param int $userId
     * @return string
     */
    private function getLocationKey(int $userId): string
    {
        return self::LOCATION_PREFIX . $userId;
    }

    /**
     * Get locations within radius (requires Redis Geo commands).
     * Note: This is an advanced feature that requires storing locations using GEOADD.
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @return array
     */
    public function getLocationsNearby(float $latitude, float $longitude, float $radiusKm = 5): array
    {
        // This would require implementing Redis Geospatial indexes
        // For now, returning empty array - can be implemented if needed
        return [];
    }
}
