<?php

namespace App\Services;

use App\Models\LocationUpdate;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class LocationRedisService
{
    protected const LOCATION_KEY_PREFIX = 'location:user:';
    protected const ACTIVE_USERS_SET = 'location:active_users';
    protected const SYNC_QUEUE_KEY = 'location:sync_queue';

    protected $redis;
    protected int $ttl;

    public function __construct()
    {
        $this->redis = Redis::connection();
        $this->ttl = (int) config('gps.location_ttl', 3600);
    }

    /**
     * Store or update a user's location in Redis.
     * Optimized for high-frequency writes.
     */
    public function storeLocation(int $userId, array $locationData): bool
    {
        $key = $this->getLocationKey($userId);
        $timestamp = now()->timestamp;

        // Prepare location data with metadata
        $data = array_merge($locationData, [
            'user_id' => $userId,
            'updated_at' => $timestamp,
            'recorded_at' => $locationData['recorded_at'] ?? $timestamp,
        ]);

        // Use pipeline for atomic operations
        $pipe = $this->redis->pipeline();

        // Store location data as hash (more memory efficient than JSON)
        $pipe->hmset($key, $data);

        // Set expiration (auto-cleanup inactive users)
        $pipe->expire($key, $this->ttl);

        // Add user to active users sorted set (score = timestamp)
        $pipe->zadd(self::ACTIVE_USERS_SET, $timestamp, $userId);

        // Add to sync queue for MySQL persistence
        $pipe->zadd(self::SYNC_QUEUE_KEY, $timestamp, $userId);

        $pipe->execute();

        return true;
    }

    /**
     * Get a user's latest location from Redis.
     */
    public function getLocation(int $userId): ?array
    {
        $key = $this->getLocationKey($userId);
        $data = $this->redis->hgetall($key);

        if (empty($data)) {
            return null;
        }

        return $this->formatLocationData($data);
    }

    /**
     * Get multiple users' locations in a single operation.
     * Optimized for bulk reads.
     */
    public function getMultipleLocations(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $pipe = $this->redis->pipeline();

        foreach ($userIds as $userId) {
            $pipe->hgetall($this->getLocationKey($userId));
        }

        $results = $pipe->execute();
        $locations = [];

        foreach ($results as $index => $data) {
            if (!empty($data)) {
                $locations[$userIds[$index]] = $this->formatLocationData($data);
            }
        }

        return $locations;
    }

    /**
     * Get all active users (users with non-expired locations).
     */
    public function getActiveUsers(int $limit = null, int $offset = 0): array
    {
        $args = [self::ACTIVE_USERS_SET, '-inf', '+inf', 'WITHSCORES', 'REV'];

        if ($limit !== null) {
            $args = array_merge($args, ['LIMIT', $offset, $limit]);
        }

        $results = $this->redis->zrangebyscore(...$args);

        $users = [];
        for ($i = 0; $i < count($results); $i += 2) {
            $users[] = [
                'user_id' => (int) $results[$i],
                'last_update' => (int) $results[$i + 1],
            ];
        }

        return $users;
    }

    /**
     * Get count of active users.
     */
    public function getActiveUserCount(): int
    {
        return (int) $this->redis->zcard(self::ACTIVE_USERS_SET);
    }

    /**
     * Get all locations for active users.
     */
    public function getAllActiveLocations(int $limit = 10000): array
    {
        $activeUsers = $this->getActiveUsers($limit);
        $userIds = array_column($activeUsers, 'user_id');

        return $this->getMultipleLocations($userIds);
    }

    /**
     * Get users that need to be synced to MySQL.
     */
    public function getUsersForSync(int $limit = 1000): array
    {
        // Get users from sync queue
        $userIds = $this->redis->zrange(self::SYNC_QUEUE_KEY, 0, $limit - 1);

        if (empty($userIds)) {
            return [];
        }

        // Get their location data
        return $this->getMultipleLocations($userIds);
    }

    /**
     * Remove users from sync queue after successful sync.
     */
    public function markAsSynced(array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        $this->redis->zrem(self::SYNC_QUEUE_KEY, ...$userIds);
    }

    /**
     * Remove expired users from active users set.
     */
    public function cleanupExpiredUsers(): int
    {
        $cutoffTime = now()->subSeconds($this->ttl)->timestamp;
        return $this->redis->zremrangebyscore(self::ACTIVE_USERS_SET, '-inf', $cutoffTime);
    }

    /**
     * Delete a user's location from Redis.
     */
    public function deleteLocation(int $userId): bool
    {
        $key = $this->getLocationKey($userId);

        $pipe = $this->redis->pipeline();
        $pipe->del($key);
        $pipe->zrem(self::ACTIVE_USERS_SET, $userId);
        $pipe->zrem(self::SYNC_QUEUE_KEY, $userId);
        $pipe->execute();

        return true;
    }

    /**
     * Get Redis key for a user's location.
     */
    protected function getLocationKey(int $userId): string
    {
        return self::LOCATION_KEY_PREFIX . $userId;
    }

    /**
     * Format raw Redis data into structured array.
     */
    protected function formatLocationData(array $data): array
    {
        return [
            'user_id' => (int) ($data['user_id'] ?? 0),
            'latitude' => (float) ($data['latitude'] ?? 0),
            'longitude' => (float) ($data['longitude'] ?? 0),
            'accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            'altitude' => isset($data['altitude']) ? (float) $data['altitude'] : null,
            'speed' => isset($data['speed']) ? (float) $data['speed'] : null,
            'heading' => isset($data['heading']) ? (float) $data['heading'] : null,
            'recorded_at' => isset($data['recorded_at']) ? Carbon::createFromTimestamp($data['recorded_at'])->toIso8601String() : null,
            'updated_at' => isset($data['updated_at']) ? Carbon::createFromTimestamp($data['updated_at'])->toIso8601String() : null,
        ];
    }

    /**
     * Get system statistics.
     */
    public function getStats(): array
    {
        return [
            'active_users' => $this->getActiveUserCount(),
            'pending_sync' => $this->redis->zcard(self::SYNC_QUEUE_KEY),
            'memory_used' => $this->redis->info('memory')['used_memory_human'] ?? 'N/A',
        ];
    }
}
