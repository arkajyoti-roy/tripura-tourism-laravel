<?php

namespace App\Console\Commands;

use App\Services\ExternalHotelSyncService;
use Illuminate\Console\Command;

class SyncExternalHotels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hotels:sync-external 
                            {--since= : Optional custom cutoff timestamp (YYYY-MM-DD HH:MM:SS)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Hotels & Resorts (service code 73) from tripuratourism.gov.in into accommodation table (type = 2)';

    /**
     * Execute the console command.
     */
    public function handle(ExternalHotelSyncService $service): int
    {
        $since = $this->option('since');

        $this->info("Fetching Hotels & Resorts (Service Code 73)...");

        $result = $service->sync($since);

        if (! $result['success']) {
            $this->error("Sync failed: " . ($result['message'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $this->info("Sync completed successfully!");
        $this->table(
            ['Service Code', 'Cutoff Used', 'Records Received', 'Records Synced'],
            [[
                $result['service_code'],
                $result['cutoff_used'],
                $result['records_received'],
                $result['records_synced'],
            ]]
        );

        if (! empty($result['errors'])) {
            $this->warn("Encountered " . count($result['errors']) . " item-level error(s).");
        }

        return self::SUCCESS;
    }
}

