<?php

use App\Models\User;
use App\Services\StripeService;
use Tests\Helpers\FakeStripeService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Helper: bind FakeStripeService into the container
|--------------------------------------------------------------------------
*/

function fakeStripe(): FakeStripeService
{
    $fake = new FakeStripeService;
    app()->instance(StripeService::class, $fake);

    return $fake;
}

/*
|--------------------------------------------------------------------------
| GET /api/account/payment-methods
|--------------------------------------------------------------------------
*/

test('list payment methods returns 401 without auth', function () {
    $response = getJson('/api/account/payment-methods');

    $response->assertUnauthorized();
});

test('list payment methods returns empty data when no methods saved', function () {
    $fake = fakeStripe();
    $user = User::factory()->create(['stripe_customer_id' => 'cus_existing']);

    $response = actingAs($user)->getJson('/api/account/payment-methods');

    $response->assertOk()
        ->assertJsonPath('data', []);
});

test('list payment methods returns formatted card data', function () {
    $fake = fakeStripe()->withPaymentMethods([
        [
            'id' => 'pm_visa_001',
            'object' => 'payment_method',
            'card' => [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2030,
            ],
        ],
        [
            'id' => 'pm_mc_002',
            'object' => 'payment_method',
            'card' => [
                'brand' => 'mastercard',
                'last4' => '5555',
                'exp_month' => 6,
                'exp_year' => 2028,
            ],
        ],
    ]);

    $user = User::factory()->create(['stripe_customer_id' => 'cus_existing']);

    $response = actingAs($user)->getJson('/api/account/payment-methods');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 'pm_visa_001')
        ->assertJsonPath('data.0.brand', 'visa')
        ->assertJsonPath('data.0.last4', '4242')
        ->assertJsonPath('data.0.expMonth', 12)
        ->assertJsonPath('data.0.expYear', 2030)
        ->assertJsonPath('data.1.id', 'pm_mc_002')
        ->assertJsonPath('data.1.brand', 'mastercard')
        ->assertJsonPath('data.1.last4', '5555');
});

test('list payment methods creates Stripe customer on first call when user has no stripe_customer_id', function () {
    $fake = fakeStripe();
    $user = User::factory()->create([
        'email' => 'newuser@finalcut.test',
        'stripe_customer_id' => null,
    ]);

    $response = actingAs($user)->getJson('/api/account/payment-methods');

    $response->assertOk();

    // Should have called getOrCreateCustomer
    expect($fake->createdCustomers)->toHaveCount(1);
    expect($fake->createdCustomers[0]['email'])->toBe('newuser@finalcut.test');
    expect($fake->createdCustomers[0]['existingCustomerId'])->toBeNull();

    // Should have persisted the stripe_customer_id on the user
    $user->refresh();
    expect($user->stripe_customer_id)->not->toBeNull();
});

test('list payment methods uses existing stripe_customer_id on subsequent calls', function () {
    $fake = fakeStripe();
    $user = User::factory()->create(['stripe_customer_id' => 'cus_already_set']);

    $response = actingAs($user)->getJson('/api/account/payment-methods');

    $response->assertOk();

    // Should NOT have called getOrCreateCustomer (no customer creation needed)
    expect($fake->createdCustomers)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| POST /api/account/payment-methods
|--------------------------------------------------------------------------
*/

test('store payment method returns 401 without auth', function () {
    $response = postJson('/api/account/payment-methods');

    $response->assertUnauthorized();
});

test('store payment method returns client secret for setup intent', function () {
    $fake = fakeStripe();
    $user = User::factory()->create(['stripe_customer_id' => 'cus_existing']);

    $response = actingAs($user)->postJson('/api/account/payment-methods');

    $response->assertOk()
        ->assertJsonPath('data.clientSecret', 'seti_fake_xxx_secret_xxx');

    expect($fake->createdSetupIntents)->toHaveCount(1);
    expect($fake->createdSetupIntents[0]['customerId'])->toBe('cus_existing');
});

test('store payment method creates Stripe customer if not exists and persists ID', function () {
    $fake = fakeStripe();
    $user = User::factory()->create([
        'email' => 'brand-new@finalcut.test',
        'stripe_customer_id' => null,
    ]);

    $response = actingAs($user)->postJson('/api/account/payment-methods');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['clientSecret']]);

    // Customer was created
    expect($fake->createdCustomers)->toHaveCount(1);
    expect($fake->createdCustomers[0]['email'])->toBe('brand-new@finalcut.test');

    // stripe_customer_id persisted
    $user->refresh();
    expect($user->stripe_customer_id)->not->toBeNull();
});

test('store payment method uses existing customer if already set', function () {
    $fake = fakeStripe();
    $user = User::factory()->create(['stripe_customer_id' => 'cus_reuse']);

    $response = actingAs($user)->postJson('/api/account/payment-methods');

    $response->assertOk();
    expect($fake->createdCustomers)->toHaveCount(0);
    expect($fake->createdSetupIntents[0]['customerId'])->toBe('cus_reuse');
});

/*
|--------------------------------------------------------------------------
| DELETE /api/account/payment-methods/{id}
|--------------------------------------------------------------------------
*/

test('delete payment method returns 401 without auth', function () {
    $response = deleteJson('/api/account/payment-methods/pm_test_123');

    $response->assertUnauthorized();
});

test('delete payment method detaches and returns success', function () {
    $fake = fakeStripe()->withRetrievedPmCustomer('cus_owner');
    $user = User::factory()->create(['stripe_customer_id' => 'cus_owner']);

    $response = actingAs($user)->deleteJson('/api/account/payment-methods/pm_to_delete');

    $response->assertOk()
        ->assertJsonPath('data.success', true);

    expect($fake->retrievedPaymentMethods)->toHaveCount(1);
    expect($fake->retrievedPaymentMethods[0]['paymentMethodId'])->toBe('pm_to_delete');
    expect($fake->detachedPaymentMethods)->toHaveCount(1);
    expect($fake->detachedPaymentMethods[0]['paymentMethodId'])->toBe('pm_to_delete');
});

test('delete payment method returns 404 when PM customer does not match user stripe_customer_id', function () {
    $fake = fakeStripe()->withRetrievedPmCustomer('cus_someone_else');
    $user = User::factory()->create(['stripe_customer_id' => 'cus_owner']);

    $response = actingAs($user)->deleteJson('/api/account/payment-methods/pm_not_mine');

    $response->assertNotFound()
        ->assertJsonStructure(['errors' => [['message']]]);

    // Should NOT have detached
    expect($fake->detachedPaymentMethods)->toHaveCount(0);
});

test('delete payment method returns 403 when user has no stripe_customer_id', function () {
    fakeStripe();
    $user = User::factory()->create(['stripe_customer_id' => null]);

    $response = actingAs($user)->deleteJson('/api/account/payment-methods/pm_any');

    $response->assertForbidden()
        ->assertJsonStructure(['errors' => [['message']]]);
});

/*
|--------------------------------------------------------------------------
| Stripe error handling
|--------------------------------------------------------------------------
*/

test('list payment methods returns 502 when Stripe is unavailable', function () {
    fakeStripe()->shouldFailWithApiError('Stripe service unavailable');
    $user = User::factory()->create(['stripe_customer_id' => 'cus_existing']);

    $response = actingAs($user)->getJson('/api/account/payment-methods');

    $response->assertStatus(502)
        ->assertJsonPath('errors.0.field', 'stripe');
});

test('list payment methods returns 400 for invalid Stripe request', function () {
    fakeStripe()->shouldFailWithInvalidRequest('No such customer: cus_deleted');
    $user = User::factory()->create(['stripe_customer_id' => 'cus_deleted']);

    $response = actingAs($user)->getJson('/api/account/payment-methods');

    $response->assertStatus(400)
        ->assertJsonPath('errors.0.field', 'stripe');
});

test('store payment method returns 502 when Stripe is unavailable', function () {
    fakeStripe()->shouldFailWithApiError('Stripe service unavailable');
    $user = User::factory()->create(['stripe_customer_id' => 'cus_existing']);

    $response = actingAs($user)->postJson('/api/account/payment-methods');

    $response->assertStatus(502)
        ->assertJsonPath('errors.0.field', 'stripe');
});

test('delete payment method returns 400 for invalid payment method ID', function () {
    fakeStripe()->shouldFailWithInvalidRequest('No such payment method: pm_nonexistent');
    $user = User::factory()->create(['stripe_customer_id' => 'cus_existing']);

    $response = actingAs($user)->deleteJson('/api/account/payment-methods/pm_nonexistent');

    $response->assertStatus(400)
        ->assertJsonPath('errors.0.field', 'stripe');
});

test('delete payment method returns 502 when Stripe is unavailable', function () {
    fakeStripe()->shouldFailWithApiError('Stripe service unavailable');
    $user = User::factory()->create(['stripe_customer_id' => 'cus_existing']);

    $response = actingAs($user)->deleteJson('/api/account/payment-methods/pm_any');

    $response->assertStatus(502)
        ->assertJsonPath('errors.0.field', 'stripe');
});
