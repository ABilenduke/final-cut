<?php

use App\Enums\GiftCardStatus;
use App\Models\GiftCard;
use Illuminate\Support\Str;

it('creates a gift card with UUID primary key', function () {
    $card = GiftCard::factory()->create();
    expect($card->id)->toBeString();
    expect(Str::isUuid($card->id))->toBeTrue();
});

it('enforces unique code constraint', function () {
    GiftCard::factory()->create(['code' => 'TESTCODE1234']);
    GiftCard::factory()->create(['code' => 'TESTCODE1234']);
})->throws(\Illuminate\Database\QueryException::class);

it('stores balances as integers (cents)', function () {
    $card = GiftCard::factory()->create([
        'initial_balance' => 5000,
        'current_balance' => 3500,
    ]);
    expect($card->initial_balance)->toBe(5000);
    expect($card->current_balance)->toBe(3500);
});

it('casts status to GiftCardStatus enum', function () {
    $card = GiftCard::factory()->create(['status' => 'depleted']);
    expect($card->status)->toBe(GiftCardStatus::Depleted);
});

it('casts purchased_at to datetime', function () {
    $card = GiftCard::factory()->create();
    expect($card->purchased_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('defaults status to active', function () {
    $card = GiftCard::factory()->create();
    expect($card->status)->toBe(GiftCardStatus::Active);
});
