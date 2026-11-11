<?php

namespace App\Services;

use App\Models\Guide;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalGuideSyncService
{
    /**
     * Service code for Tourist Guides on external portal.
     */
    public const GUIDE_SERVICE_CODE = 74;

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
     * Sync Tourist Guides.
     */
    public function sync(?string $since = null): array
    {
        $code = self::GUIDE_SERVICE_CODE;
        $url = "{$this->baseUrl}/{$code}";

        // Isolated cutoff timestamp strictly from external guide syncs
        $cutoff = $since ?: $this->resolveCutoffTimestamp();

        Log::info("Starting external guide sync (code {$code}) from [{$url}] with cutoff: {$cutoff}");

        $response = $this->fetchFromExternal($url, $cutoff);

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::error("External guide sync failed [{$status}]: {$body}");

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
                Log::warning("Failed to sync guide record: " . $e->getMessage(), ['record' => $item]);
                $errors[] = $e->getMessage();
            }
        }

        Log::info("External guide sync completed: {$syncedCount} records synced.");

        return [
            'success' => true,
            'service_code' => $code,
            'cutoff_used' => $cutoff,
            'records_received' => count($records),
            'records_synced' => $syncedCount,
            'errors' => $errors,
        ];
    }

    protected function resolveCutoffTimestamp(): string
    {
        $latest = Guide::query()->max('external_synced_at');

        if ($latest) {
            try {
                return Carbon::parse($latest)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
            }
        }

        return config('services.tripura_tourism.default_since', '2020-01-01 00:00:00');
    }

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

    protected function extractRecords(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        if (array_is_list($json)) {
            return $json;
        }

        foreach (['data', 'applications', 'records', 'result', 'items'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                return array_is_list($json[$key]) ? $json[$key] : array_values($json[$key]);
            }
        }

        return [];
    }

    protected function syncSingleRecord(array $item): ?Guide
    {
        $name = $this->firstNonEmpty($item, [
            'user_name', 'name', 'applicant_name', 'guide_name',
        ]);

        if (empty($name)) {
            return null;
        }

        $phone = $this->firstNonEmpty($item, [
            'phone', 'mobile', 'mobile_no', 'contact', 'contact_no',
        ]);

        $email = $this->firstNonEmpty($item, [
            'email', 'email_id', 'mail',
        ]);

        $address = $this->firstNonEmpty($item, [
            'address', 'registered_address', 'full_address',
        ]);

        $validDate = $this->firstNonEmpty($item, [
            'valid_upto', 'validDate', 'expiry_date', 'valid_to',
        ]);

        $language = $this->firstNonEmpty($item, ['language', 'languages', 'known_languages']);
        $spot = $this->firstNonEmpty($item, ['spot', 'tourist_spots', 'locations']);

        $externalUpdatedAt = $this->firstNonEmpty($item, ['updated_at', 'update_date']);

        $identity = [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
        ];

        $guide = Guide::query()->forIdentity($identity)->orderByDesc('id')->first();

        if (! $guide) {
            $guide = new Guide();
        }

        $guide->name = $name;
        $guide->phone = $phone;
        $guide->email = $email;
        $guide->address = $address;
        $guide->language = $language ?: $guide->language;
        $guide->spot = $spot ?: ($guide->spot ?: 'All tourist destinations within the state');

        if ($validDate) {
            $guide->validDate = $this->normalizeDate($validDate);
        }

        if (! $guide->hasManualStatus()) {
            $guide->is_active = $this->calculateActiveStatus($guide->validDate);
        }

        if ($externalUpdatedAt) {
            try {
                $syncTimestamp = Carbon::parse($externalUpdatedAt);
            } catch (\Throwable $e) {
                $syncTimestamp = now();
            }
        } else {
            $syncTimestamp = now();
        }

        $guide->external_synced_at = $syncTimestamp;
        $guide->updated_at = $syncTimestamp;

        $guide->save();

        return $guide;
    }

    protected function firstNonEmpty(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && trim((string) $item[$key]) !== '') {
                return trim((string) $item[$key]);
            }
        }

        return null;
    }

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

