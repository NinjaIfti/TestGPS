<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Optimized indexes for high-performance queries
            $table->index('user_id');
            $table->index('recorded_at');
            $table->index(['user_id', 'recorded_at']);

            // Spatial index for location-based queries (optional but recommended)
            // Uncomment if you need to perform proximity searches
            // $table->point('coordinates')->nullable();
            // $table->spatialIndex('coordinates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_updates');
    }
};
