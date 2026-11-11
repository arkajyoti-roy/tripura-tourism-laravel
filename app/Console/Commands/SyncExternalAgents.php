<?php

namespace App\Console\Commands;

use App\Services\ExternalAgentSyncService;
use Illuminate\Console\Command;

class SyncExternalAgents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agents:sync-external 
                            {serviceCode=75 : External service code (75 for Travel Agents, 74 for Guides, 73 for Hotels, 76 for Homestays)}
                            {--since= : Optional custom cutoff timestamp (YYYY-MM-DD HH:MM:SS)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync agents / travel operators from tripuratourism.gov.in external API';

    /**
     * Execute the console command.
     */
    public function handle(ExternalAgentSyncService $service): int
    {
        $serviceCode = (int) $this->argument('serviceCode');
        $since = $this->option('since');

        $this->info("Fetching applications for service code [{$serviceCode}]...");

        $result = $service->sync($serviceCode, $since);

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
            $this->warn("Encountered " . count($result['errors']) . " item-level error(s). Check logs for details.");
        }

        return self::SUCCESS;
    }
}

