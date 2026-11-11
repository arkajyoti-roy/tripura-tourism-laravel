<?php

use App\Models\Admin;
use App\Models\Agent;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    createAgentsTable();
});

it('lists agents in admin-managed display order on the public endpoint', function () {
    Agent::create(['name' => 'Second', 'displayorder' => 2]);
    Agent::create(['name' => 'First', 'displayorder' => 1]);

    $response = $this->getJson('/api/public/agents');

    $response->assertOk();
    expect(collect($response->json())->pluck('name')->all())->toBe(['First', 'Second']);
});

it('filters active and expired records using manual status and date fallback', function () {
    Agent::create(['name' => 'Date Active', 'validDate' => '2999-01-01']);
    Agent::create(['name' => 'Date Expired', 'validDate' => '2000-01-01']);
    Agent::create(['name' => 'Manual Active', 'validDate' => '2000-01-01', 'is_active' => true]);
    Agent::create(['name' => 'Manual Inactive', 'validDate' => '2999-01-01', 'is_active' => false]);

    $active = $this->getJson('/api/public/agents?status=active')->json();
    $expired = $this->getJson('/api/public/agents?status=expired')->json();

    expect(collect($active)->pluck('name')->sort()->values()->all())
        ->toBe(['Date Active', 'Manual Active'])
        ->and(collect($expired)->pluck('name')->sort()->values()->all())
        ->toBe(['Date Expired', 'Manual Inactive']);
});

it('creates an agent with the next display order and a timestamp', function () {
    Sanctum::actingAs(new Admin);

    Agent::create(['name' => 'Existing', 'displayorder' => 7]);

    $response = $this->postJson('/api/admin/agents', [
        'name' => 'New Agent',
        'validDate' => '2999-01-01',
    ]);

    $response->assertCreated()
        ->assertJsonPath('displayorder', 8)
        ->assertJsonPath('is_active', true);

    expect($response->json('updated_at'))->not->toBeNull();
});

it('partially updates only the status and refreshes the timestamp', function () {
    Sanctum::actingAs(new Admin);

    $agent = Agent::create(['name' => 'Keep Name', 'validDate' => '2999-01-01']);
    $previous = $agent->updated_at;

    $this->travel(2)->seconds();

    $this->postJson("/api/admin/agents/{$agent->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('is_active', false)
        ->assertJsonPath('name', 'Keep Name');

    $fresh = $agent->fresh();

    expect($fresh->name)->toBe('Keep Name')
        ->and($fresh->updated_at->greaterThan($previous))->toBeTrue();
});

it('supports the dedicated status endpoint for external updates', function () {
    Sanctum::actingAs(new Admin);

    $agent = Agent::create(['name' => 'Status Agent', 'validDate' => '2999-01-01']);

    $this->postJson("/api/admin/agents/{$agent->id}/status", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('is_active', false);

    expect($agent->fresh()->is_active)->toBeFalse();
});
