<?php

namespace App\Services;

use App\Models\Accommodation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalHotelSyncService
{
    /**
     * Service code for Hotels / Resorts on external portal.
     */
    public const HOTEL_SERVICE_CODE = 73;

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
     * Sync Private Hotels / Resorts (Accommodation type = 2).
     */
    public function sync(?string $since = null): array
    {
        $code = self::HOTEL_SERVICE_CODE;
        $url = "{$this->baseUrl}/{$code}";

        // Isolate cutoff date to only prior external hotel syncs
        $cutoff = $since ?: $this->resolveCutoffTimestamp();

        Log::info("Starting external hotel sync (code {$code}) from [{$url}] with cutoff: {$cutoff}");

        $response = $this->fetchFromExternal($url, $cutoff);

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::error("External hotel sync failed [{$status}]: {$body}");

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
                Log::warning("Failed to sync hotel record: " . $e->getMessage(), ['record' => $item]);
                $errors[] = $e->getMessage();
            }
        }

        Log::info("External hotel sync completed: {$syncedCount} records synced.");

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
        $latest = Accommodation::query()
            ->where('type', Accommodation::TYPE_PRIVATE)
            ->max('external_synced_at');

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

    protected function syncSingleRecord(array $item): ?Accommodation
    {
        $name = $this->firstNonEmpty($item, [
            'unit_name', 'name', 'hotel_name', 'resort_name', 'applicant_name', 'enterprise_name',
        ]);

        if (empty($name)) {
            return null;
        }

        $contact = $this->firstNonEmpty($item, [
            'phone', 'mobile', 'mobile_no', 'contact', 'contact_no',
        ]);

        $email = $this->firstNonEmpty($item, [
            'email', 'email_id', 'mail',
        ]);

        $address = $this->firstNonEmpty($item, [
            'address', 'registered_address', 'full_address',
        ]);

        $validUpto = $this->firstNonEmpty($item, [
            'valid_upto', 'validDate', 'expiry_date', 'valid_to',
        ]);

        $userName = $this->firstNonEmpty($item, ['user_name', 'applicant_name', 'owner_name']);

        $externalUpdatedAt = $this->firstNonEmpty($item, ['updated_at', 'update_date']);

        $identity = [
            'name' => $name,
            'contact' => $contact,
            'email' => $email,
        ];

        $acc = Accommodation::query()
            ->where('type', Accommodation::TYPE_PRIVATE)
            ->forIdentity($identity)
            ->orderByDesc('aid')
            ->first();

        if (! $acc) {
            $acc = new Accommodation();
            $acc->type = Accommodation::TYPE_PRIVATE;
            $acc->created_at = now();
        }

        $acc->name = $name;
        $acc->contact = $contact;
        $acc->email = $email;
        $acc->address = $address;
        $acc->name_of_lessee = $userName; // Clean owner name in lessee
        $acc->description = $acc->description ?: ($name . " - Hotel & Resort");

        if ($validUpto) {
            $acc->valid_upto = $this->normalizeDate($validUpto);
        }

        if (! $acc->hasManualStatus()) {
            $acc->is_active = $this->calculateActiveStatus($acc->valid_upto);
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

        $acc->external_synced_at = $syncTimestamp;
        $acc->updated_at = $syncTimestamp;

        $acc->save();

        return $acc;
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
            if (is_numeric($validDate) && strlen($validDate) >= 9) {
                return Carbon::createFromTimestamp((int) $validDate)->endOfDay()->greaterThanOrEqualTo(Carbon::today());
            }

            $parsed = Carbon::parse($validDate)->startOfDay();
            return $parsed->greaterThanOrEqualTo(Carbon::today());
        } catch (\Throwable $e) {
            return true;
        }
    }
}

