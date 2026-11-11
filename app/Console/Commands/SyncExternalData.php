<?php

namespace App\Console\Commands;

use App\Services\ExternalAgentSyncService;
use App\Services\ExternalGuideSyncService;
use App\Services\ExternalHomestaySyncService;
use App\Services\ExternalHotelSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncExternalData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tourism:sync-external 
                            {--since=2025-01-01 00:00:00 : Initial cutoff timestamp}
                            {--service=all : Specific service to sync: all, agents, homestays, hotels, guides}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync external portal data for Agents (75), Homestays (76), Hotels (73), and Guides (74)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $since = $this->option('since');
        $service = strtolower((string) $this->option('service'));

        $this->info("==================================================");
        $this->info("Starting Tourism External Sync");
        $this->info("Cutoff timestamp (--since): {$since}");
        $this->info("Target service: {$service}");
        $this->info("==================================================");

        $services = [
            'agents' => [
                'name' => 'Travel Agents (Code 75)',
                'code' => 75,
                'class' => ExternalAgentSyncService::class,
            ],
            'homestays' => [
                'name' => 'Homestays (Code 76)',
                'code' => 76,
                'class' => ExternalHomestaySyncService::class,
            ],
            'hotels' => [
                'name' => 'Hotels / Resorts (Code 73)',
                'code' => 73,
                'class' => ExternalHotelSyncService::class,
            ],
            'guides' => [
                'name' => 'Tourist Guides (Code 74)',
                'code' => 74,
                'class' => ExternalGuideSyncService::class,
            ],
        ];

        foreach ($services as $key => $svc) {
            if ($service !== 'all' && $service !== $key) {
                continue;
            }

            $this->line("");
            $this->comment("--> Syncing {$svc['name']}...");

            try {
                $syncInstance = app($svc['class']);
                $result = ($key === 'agents') 
                    ? $syncInstance->sync(null, $since) 
                    : $syncInstance->sync($since);

                $received = $result['records_received'] ?? 0;
                $synced = $result['records_synced'] ?? 0;
                $status = ($result['success'] ?? false) ? 'success' : 'failed';

                if ($result['success'] ?? false) {
                    $this->info("    [SUCCESS] Received: {$received}, Synced: {$synced}");
                } else {
                    $this->error("    [FAILED] " . ($result['message'] ?? 'Unknown error'));
                }

                // Log into sync_logs table
                DB::table('sync_logs')->insert([
                    'sync_type' => $key,
                    'service_code' => $svc['code'],
                    'last_synced_at' => now(),
                    'records_received' => $received,
                    'records_synced' => $synced,
                    'status' => $status,
                    'message' => $result['message'] ?? "CLI sync --since={$since}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            } catch (\Throwable $e) {
                $this->error("    [EXCEPTION] " . $e->getMessage());
                DB::table('sync_logs')->insert([
                    'sync_type' => $key,
                    'service_code' => $svc['code'],
                    'last_synced_at' => now(),
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->line("");
        $this->info("==================================================");
        $this->info("All sync tasks completed & logged in sync_logs.");
        $this->info("==================================================");

        return Command::SUCCESS;
    }
}

