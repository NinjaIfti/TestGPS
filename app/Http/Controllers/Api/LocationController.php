<?php

namespace App\Http\Controllers\Api;

use App\Events\LocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    protected LocationService $locationService;

    /**
     * Create a new LocationController instance.
     */
    public function __construct(LocationService $locationService)
    {
        $this->middleware('auth:api');
        $this->locationService = $locationService;
    }

    /**
     * Store/Update user's GPS location.
     * This endpoint is called every second by 10k+ users.
     * Optimized for high-frequency writes.
     *
     * @param StoreLocationRequest $request
     * @return JsonResponse
     */
    public function update(StoreLocationRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        // Prepare location data
        $locationData = [
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'altitude' => $request->altitude,
            'accuracy' => $request->accuracy,
            'speed' => $request->speed,
            'heading' => $request->heading,
            'recorded_at' => $request->recorded_at ?? now()->toIso8601String(),
        ];

        // Store in Redis (fast write) and get normalized payload
        $normalizedLocation = $this->locationService->storeLocation($user->id, $locationData);

        // Broadcast location update via WebSocket when enabled
        if (config('gps.broadcast_enabled', true)) {
            broadcast(new LocationUpdated($user->id, $normalizedLocation))->toOthers();
        }

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
            'data' => $normalizedLocation,
        ], 200);
    }

    /**
     * Get current user's latest location.
     *
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        $user = auth('api')->user();
        $location = $this->locationService->getLocation($user->id);

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'No location data found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $location,
        ]);
    }

    /**
     * Get specific user's location (Admin only).
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function showUser(int $userId): JsonResponse
    {
        $currentUser = auth('api')->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $location = $this->locationService->getLocation($userId);

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'No location data found for this user',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $location,
        ]);
    }

    /**
     * Get all active users' locations (Admin only).
     * Returns locations from Redis for fast read.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = auth('api')->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $limit = min((int) $request->input('limit', 10000), 20000);
        $locations = $this->locationService->getAllActiveLocations($limit);
        $activeCount = $this->locationService->getActiveUserCount();

        return response()->json([
            'success' => true,
            'data' => [
                'locations' => $locations,
                'total_active_users' => $activeCount,
                'showing' => count($locations),
            ],
        ]);
    }

    /**
     * Get statistics about active tracking (Admin only).
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $currentUser = auth('api')->user();

        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $stats = $this->locationService->getSystemStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Delete user's location from Redis.
     *
     * @return JsonResponse
     */
    public function destroy(): JsonResponse
    {
        $user = auth('api')->user();
        $this->locationService->deleteLocation($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Location data deleted successfully',
        ]);
    }
}
