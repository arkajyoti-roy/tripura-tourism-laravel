<?php

use App\Models\Agent;

it('defaults to the expiry date and allows a manual override for active status', function () {
    createAgentsTable();

    $activeByDate = Agent::create([
        'name' => 'Auto active',
        'validDate' => '2099-01-01',
    ]);

    $expiredByDate = Agent::create([
        'name' => 'Expired by date',
        'validDate' => '2000-01-01',
    ]);

    $manualInactive = Agent::create([
        'name' => 'Manual inactive',
        'validDate' => '2099-01-01',
        'is_active' => false,
    ]);

    $manualActive = Agent::create([
        'name' => 'Manual active',
        'validDate' => '2000-01-01',
        'is_active' => true,
    ]);

    expect($activeByDate->is_active)->toBeTrue()
        ->and($expiredByDate->is_active)->toBeFalse()
        ->and($manualInactive->is_active)->toBeFalse()
        ->and($manualActive->is_active)->toBeTrue();
});

it('treats the newest record for a person as current and deactivates older ones', function () {
    createAgentsTable();

    $old = Agent::create([
        'name' => 'Same Person',
        'phone' => '9999999999',
        'email' => 'person@example.com',
        'validDate' => '2099-01-01',
    ]);

    $new = Agent::create([
        'name' => 'Same Person',
        'phone' => '9999999999',
        'email' => 'person@example.com',
        'validDate' => '2099-01-01',
    ]);

    $old->refresh();
    $new->refresh();

    expect($old->is_active)->toBeFalse()
        ->and($new->is_active)->toBeTrue()
        ->and($old->is_current)->toBeFalse()
        ->and($new->is_current)->toBeTrue()
        ->and($old->updated_at)->not->toBeNull()
        ->and($new->updated_at)->not->toBeNull();
});

it('assigns the next display order to new records automatically', function () {
    createAgentsTable();

    $first = Agent::create(['name' => 'First']);
    $second = Agent::create(['name' => 'Second']);

    $manual = Agent::create(['name' => 'Manual', 'displayorder' => 10]);
    $afterManual = Agent::create(['name' => 'After Manual']);

    expect($first->displayorder)->toBe(1)
        ->and($second->displayorder)->toBe(2)
        ->and($manual->displayorder)->toBe(10)
        ->and($afterManual->displayorder)->toBe(11);
});

it('refreshes updated_at when the status is changed manually', function () {
    createAgentsTable();

    $agent = Agent::create([
        'name' => 'Manual Change',
        'validDate' => '2099-01-01',
    ]);

    $previous = $agent->updated_at;

    $agent->is_active = false;
    $agent->updated_at = now()->addMinute();
    $agent->save();

    expect($agent->fresh()->is_active)->toBeFalse()
        ->and($agent->fresh()->updated_at->greaterThan($previous))->toBeTrue();
});
