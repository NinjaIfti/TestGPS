<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('latitude', 10, 8)->comment('GPS Latitude');
            $table->decimal('longitude', 11, 8)->comment('GPS Longitude');
            $table->decimal('altitude', 8, 2)->nullable()->comment('Altitude in meters');
            $table->decimal('accuracy', 8, 2)->nullable()->comment('Accuracy in meters');
            $table->decimal('speed', 8, 2)->nullable()->comment('Speed in m/s');
            $table->decimal('heading', 5, 2)->nullable()->comment('Direction in degrees');
            $table->timestamp('recorded_at')->comment('GPS timestamp from device');
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('recorded_at');
            $table->index(['user_id', 'recorded_at']);

            // Spatial index for location queries (if using MySQL 5.7+)
            // $table->point('coordinates')->nullable();
            // $table->spatialIndex('coordinates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_locations');
    }
};
