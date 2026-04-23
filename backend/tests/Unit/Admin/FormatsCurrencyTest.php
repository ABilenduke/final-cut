<?php

use App\Filament\Concerns\FormatsCurrency;

$fixture = new class
{
    use FormatsCurrency;
};

test('centsToDisplay formats a positive integer as a dollar string', function () use ($fixture): void {
    expect($fixture::centsToDisplay(1299))->toBe('$12.99');
});

test('centsToDisplay formats zero as $0.00', function () use ($fixture): void {
    expect($fixture::centsToDisplay(0))->toBe('$0.00');
});

test('centsToDisplay returns em-dash for null', function () use ($fixture): void {
    expect($fixture::centsToDisplay(null))->toBe('—');
});

test('displayToCents parses a dollar string into cents', function () use ($fixture): void {
    expect($fixture::displayToCents('$12.99'))->toBe(1299);
});

// Intentional: displayToCents treats the input as a dollar amount.
// '1299.00' parses as $1,299.00 → 129,900 cents, not as cents.
test('displayToCents treats numeric input as dollars, not cents', function () use ($fixture): void {
    expect($fixture::displayToCents('1299.00'))->toBe(129900);
});

test('displayToCents returns null for null input', function () use ($fixture): void {
    expect($fixture::displayToCents(null))->toBeNull();
});

test('centsToDisplay and displayToCents round-trip', function () use ($fixture): void {
    $formatted = $fixture::centsToDisplay(1299);
    expect($fixture::displayToCents($formatted))->toBe(1299);
});

test('displayToCents returns null for input with no digits', function () use ($fixture): void {
    expect($fixture::displayToCents('abc'))->toBeNull();
    expect($fixture::displayToCents('$'))->toBeNull();
    expect($fixture::displayToCents('   '))->toBeNull();
});

test('displayToCents preserves a leading minus sign', function () use ($fixture): void {
    expect($fixture::displayToCents('-$12.99'))->toBe(-1299);
    expect($fixture::displayToCents('-1299.00'))->toBe(-129900);
});

test('displayToCents trims surrounding whitespace', function () use ($fixture): void {
    expect($fixture::displayToCents('  $12.99  '))->toBe(1299);
});
