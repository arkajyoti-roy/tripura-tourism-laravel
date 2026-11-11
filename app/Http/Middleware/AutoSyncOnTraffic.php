<?php

namespace App\Http\Middleware;

use App\Services\ExternalAgentSyncService;
use App\Services\ExternalHomestaySyncService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoSyncOnTraffic
{
    /**
     * How often to trigger external sync (24 hours).
     */
    protected int $intervalHours = 24;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Run sync check in the background after sending response to the user
        // so that the visitor experiences ZERO delay.
        $this->checkAndTriggerSync();

        return $response;
    }

    /**
     * Check if 24 hours have passed since the last sync using sync_logs table.
     */
    protected function checkAndTriggerSync(): void
    {
        try {
            $lock = Cache::lock('external_sync_lock', 60);

            // If another request is currently executing the sync, skip
            if (! $lock->get()) {
                return;
            }

            $services = [
                [
                    'type' => 'agents',
                    'code' => 75,
                    'service_class' => ExternalAgentSyncService::class,
                ],
                [
                    'type' => 'homestays',
                    'code' => 76,
                    'service_class' => ExternalHomestaySyncService::class,
                ],
                [
                    'type' => 'hotels',
                    'code' => 73,
                    'service_class' => \App\Services\ExternalHotelSyncService::class,
                ],
                [
                    'type' => 'guides',
                    'code' => 74,
                    'service_class' => \App\Services\ExternalGuideSyncService::class,
                ],
            ];

            foreach ($services as $svc) {
                // Check each service's individual last successful sync from sync_logs
                $lastSyncRecord = DB::table('sync_logs')
                    ->where('service_code', $svc['code'])
                    ->where('status', 'success')
                    ->orderByDesc('id')
                    ->first();

                $shouldSync = false;
                if (! $lastSyncRecord || ! $lastSyncRecord->last_synced_at) {
                    $shouldSync = true;
                } else {
                    $lastTime = Carbon::parse($lastSyncRecord->last_synced_at);
                    if (now()->diffInHours($lastTime) >= $this->intervalHours) {
                        $shouldSync = true;
                    }
                }

                if (! $shouldSync) {
                    continue;
                }

                Log::info("24 hours threshold reached on user traffic for service [{$svc['type']} / {$svc['code']}]. Initiating auto-sync...");
                $now = now();

                try {
                    $result = app($svc['service_class'])->sync();
                    DB::table('sync_logs')->insert([
                        'sync_type' => $svc['type'],
                        'service_code' => $svc['code'],
                        'last_synced_at' => $now,
                        'records_received' => $result['records_received'] ?? 0,
                        'records_synced' => $result['records_synced'] ?? 0,
                        'status' => ($result['success'] ?? false) ? 'success' : 'failed',
                        'message' => $result['message'] ?? 'Traffic-driven 24hr auto-sync',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Traffic sync failed for {$svc['type']}: " . $e->getMessage());
                    DB::table('sync_logs')->insert([
                        'sync_type' => $svc['type'],
                        'service_code' => $svc['code'],
                        'last_synced_at' => $now,
                        'status' => 'failed',
                        'message' => $e->getMessage(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $lock->release();
        } catch (\Throwable $e) {
            Log::warning("AutoSyncOnTraffic error: " . $e->getMessage());
        }
    }
}
