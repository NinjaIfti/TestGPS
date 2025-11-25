<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'altitude',
        'speed',
        'heading',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'altitude' => 'float',
            'speed' => 'float',
            'heading' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the location update.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convert to array format for API responses.
     */
    public function toLocationArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'altitude' => $this->altitude,
            'speed' => $this->speed,
            'heading' => $this->heading,
            'recorded_at' => $this->recorded_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
