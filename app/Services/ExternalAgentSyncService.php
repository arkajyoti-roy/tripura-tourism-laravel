<?php

namespace App\Services;

use App\Models\Agent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalAgentSyncService
{
    /**
     * Default service code for Tour Operators / Travel Agents.
     */
    public const AGENT_SERVICE_CODE = 75;

    protected string $baseUrl;
    protected ?string $cookie;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            config('services.tripura_tourism.api_url', 'https://tripuratourism.gov.in/onlineservices/api/tourism/get-applications'),
            '/'
        );
        $this->cookie = config('services.tripura_tourism.cookie');
    }

    /**
     * Sync agents from the external service.
     *
     * @param int|null $serviceCode Defaults to 75 (Tour Operators / Travel Agents)
     * @param string|null $since Custom timestamp cutoff; defaults to latest updated_at in DB
     * @return array Summary of the sync run
     */
    public function sync(?int $serviceCode = null, ?string $since = null): array
    {
        $code = $serviceCode ?: self::AGENT_SERVICE_CODE;
        $url = "{$this->baseUrl}/{$code}";

        // Determine the cutoff timestamp
        $cutoff = $since ?: $this->resolveCutoffTimestamp();

        Log::info("Starting external agent sync from [{$url}] with updated_at cutoff: {$cutoff}");

        $response = $this->fetchFromExternal($url, $cutoff);

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::error("External agent sync failed with status [{$status}]: {$body}");

            return [
                'success' => false,
                'status' => $status,
                'message' => "External server responded with HTTP status {$status}",
                'records_synced' => 0,
            ];
        }

        $records = $this->extractRecords($response->json());
        $syncedCount = 0;
        $errors = [];

        foreach ($records as $item) {
            try {
                $saved = $this->syncSingleRecord($item);
                if ($saved) {
                    $syncedCount++;
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to sync record: " . $e->getMessage(), ['record' => $item]);
                $errors[] = $e->getMessage();
            }
        }

        Log::info("External agent sync completed: {$syncedCount} records synced.");

        return [
            'success' => true,
            'service_code' => $code,
            'cutoff_used' => $cutoff,
            'records_received' => count($records),
            'records_synced' => $syncedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Resolve the last external_synced_at cutoff.
     * Uses ONLY the timestamp from prior external API calls, completely
     * isolated from manual admin edits and toggle updates.
     */
    protected function resolveCutoffTimestamp(): string
    {
        $latest = Agent::query()->max('external_synced_at');

        if ($latest) {
            try {
                return Carbon::parse($latest)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                // Ignore and fall back
            }
        }

        // Fallback default date if no external records have been synced yet
        return config('services.tripura_tourism.default_since', '2020-01-01 00:00:00');
    }

    /**
     * Dispatch HTTP request to the external server.
     */
    protected function fetchFromExternal(string $url, string $cutoff)
    {
        $request = Http::timeout(30)->asJson();

        if ($this->cookie) {
            $request = $request->withHeaders([
                'Cookie' => $this->cookie,
            ]);
        }

        return $request->post($url, [
            'updated_at' => $cutoff,
        ]);
    }

    /**
     * Extract the array of records from various common response wrapper shapes.
     */
    protected function extractRecords(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        // Direct list: [ {...}, {...} ]
        if (array_is_list($json)) {
            return $json;
        }

        // Wrapped in 'data' / 'applications' / 'records' / 'result'
        foreach (['data', 'applications', 'records', 'result', 'items'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                return array_is_list($json[$key]) ? $json[$key] : array_values($json[$key]);
            }
        }

        return [];
    }

    /**
     * Normalize a single external record and persist it via the Agent model.
     * The Agent model automatically handles displayorder, deactivating older duplicates,
     * and maintaining updated_at.
     */
    protected function syncSingleRecord(array $item): ?Agent
    {
        // Check unit_name (Agency/Enterprise name) or user_name (applicant name)
        $name = $this->firstNonEmpty($item, [
            'unit_name', 'name', 'applicant_name', 'agency_name', 'user_name', 'operator_name', 'firm_name', 'business_name',
        ]);

        if (empty($name)) {
            return null;
        }

        $phone = $this->firstNonEmpty($item, [
            'phone', 'mobile', 'mobile_no', 'phone_no', 'contact_no', 'contact',
        ]);

        $email = $this->firstNonEmpty($item, [
            'email', 'email_id', 'mail',
        ]);

        $address = $this->firstNonEmpty($item, [
            'address', 'registered_address', 'office_address', 'full_address',
        ]);

        $validDate = $this->firstNonEmpty($item, [
            'valid_upto', 'validDate', 'valid_to', 'valid_date', 'expiry_date', 'date',
        ]);

        $externalUpdatedAt = $this->firstNonEmpty($item, [
            'updated_at', 'update_date', 'last_modified',
        ]);

        // Look for an existing record with identical identity
        $identity = [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
        ];

        $agent = Agent::query()->forIdentity($identity)->orderByDesc('id')->first();

        if (! $agent) {
            $agent = new Agent();
        }

        $agent->name = $name;
        $agent->phone = $phone;
        $agent->email = $email;
        $agent->address = $address;

        if ($validDate) {
            $agent->validDate = $this->normalizeDate($validDate);
        }

        // Explicitly set 1 (active) or 0 (inactive) in database
        if (! $agent->hasManualStatus()) {
            $agent->is_active = $this->calculateActiveStatus($agent->validDate);
        }

        // Track strictly when this record was updated by the external API call
        if ($externalUpdatedAt) {
            try {
                $syncTimestamp = Carbon::parse($externalUpdatedAt);
            } catch (\Throwable $e) {
                $syncTimestamp = now();
            }
        } else {
            $syncTimestamp = now();
        }

        $agent->external_synced_at = $syncTimestamp;
        $agent->updated_at = $syncTimestamp;

        $agent->save();

        return $agent;
    }

    /**
     * Get the first non-empty value from an array for candidate keys.
     */
    protected function firstNonEmpty(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && trim((string) $item[$key]) !== '') {
                return trim((string) $item[$key]);
            }
        }

        return null;
    }

    /**
     * Normalize dates into YYYY-MM-DD or standard format.
     */
    protected function normalizeDate(string $rawDate): string
    {
        try {
            $cleaned = trim($rawDate);
            if (preg_match('/^\d{2}[-\/]\d{2}[-\/]\d{4}$/', $cleaned)) {
                $sep = str_contains($cleaned, '/') ? '/' : '-';
                $parts = explode($sep, $cleaned);

                return sprintf('%04d-%02d-%02d', (int) $parts[2], (int) $parts[1], (int) $parts[0]);
            }

            return Carbon::parse($cleaned)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $rawDate;
        }
    }

    /**
     * Compute explicit boolean 1 / 0 based on validity date vs today.
     */
    protected function calculateActiveStatus(?string $validDate): bool
    {
        if (empty($validDate)) {
            return true;
        }

        try {
            $parsed = Carbon::parse($validDate)->startOfDay();
            return $parsed->greaterThanOrEqualTo(Carbon::today());
        } catch (\Throwable $e) {
            return true;
        }
    }
}

