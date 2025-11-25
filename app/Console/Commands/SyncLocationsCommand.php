<?php

namespace App\Console\Commands;

use App\Jobs\SyncLocationsToDatabase;
use Illuminate\Console\Command;

class SyncLocationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync location data from Redis to MySQL database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting location sync from Redis to MySQL...');

        try {
            // Dispatch the sync job
            SyncLocationsToDatabase::dispatch();

            $this->info('Location sync job dispatched successfully!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch sync job: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
