<?php

use App\Models\Agent;
use App\Services\ExternalAgentSyncService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    createAgentsTable();
});

it('syncs agents from external api and enforces latest record wins', function () {
    Http::fake([
        'https://tripuratourism.gov.in/onlineservices/api/tourism/get-applications/75' => Http::response([
            'status' => 'success',
            'data' => [
                [
                    'applicant_name' => 'John Doe Travels',
                    'mobile_no' => '9876543210',
                    'email' => 'john@travels.com',
                    'address' => 'Agartala Central',
                    'valid_upto' => '2028-12-31',
                    'updated_at' => '2026-11-10 10:00:00',
                ],
                [
                    'name' => 'Tripura Holiday Agency',
                    'phone' => '9123456789',
                    'email' => 'contact@tripuraholidays.com',
                    'address' => 'Udaipur, Tripura',
                    'validDate' => '15/08/2027',
                    'updated_at' => '2026-11-10 10:30:00',
                ],
            ],
        ], 200),
    ]);

    $service = new ExternalAgentSyncService();
    $result = $service->sync(75);

    expect($result['success'])->toBeTrue()
        ->and($result['records_synced'])->toBe(2);

    $john = Agent::where('email', 'john@travels.com')->first();
    expect($john)->not->toBeNull()
        ->and($john->name)->toBe('John Doe Travels')
        ->and($john->validDate)->toBe('2028-12-31')
        ->and($john->is_active)->toBeTrue();

    $holiday = Agent::where('email', 'contact@tripuraholidays.com')->first();
    expect($holiday)->not->toBeNull()
        ->and($holiday->validDate)->toBe('2027-08-15')
        ->and($holiday->is_active)->toBeTrue();
});

it('deactivates older local duplicate when external sync brings a newer record', function () {
    $existing = Agent::create([
        'name' => 'John Doe Travels',
        'phone' => '9876543210',
        'email' => 'john@travels.com',
        'address' => 'Old Address',
        'validDate' => '2025-01-01',
        'is_active' => null,
    ]);
    // Force older updated_at
    $existing->updated_at = Carbon\Carbon::parse('2024-01-01 00:00:00');
    $existing->saveQuietly();

    Http::fake([
        'https://tripuratourism.gov.in/onlineservices/api/tourism/get-applications/75' => Http::response([
            'data' => [
                [
                    'applicant_name' => 'John Doe Travels',
                    'mobile_no' => '9876543210',
                    'email' => 'john@travels.com',
                    'address' => 'New Office, Agartala',
                    'valid_upto' => '2029-01-01',
                    'updated_at' => '2026-11-10 12:00:00',
                ],
            ],
        ], 200),
    ]);

    $service = new ExternalAgentSyncService();
    $result = $service->sync(75);

    expect($result['success'])->toBeTrue()
        ->and($result['records_synced'])->toBe(1);

    $existing->refresh();
    expect($existing->address)->toBe('New Office, Agartala')
        ->and($existing->validDate)->toBe('2029-01-01')
        ->and($existing->is_active)->toBeTrue();
});

