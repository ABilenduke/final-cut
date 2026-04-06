<?php

use App\Support\PayloadFingerprint;

test('deterministic hash', function () {
    $first = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', 'Enjoy');
    $second = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', 'Enjoy');

    expect($first)->toBe($second)
        ->and($first)->toHaveLength(64);
});

test('different amounts produce different hashes', function () {
    $first = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', 'Enjoy');
    $second = PayloadFingerprint::giftCard(6000, 'recipient@example.com', 'Jane Doe', 'John Doe', 'Enjoy');

    expect($first)->not->toBe($second);
});

test('normalizes email to lowercase and trims whitespace', function () {
    $first = PayloadFingerprint::giftCard(5000, '  RECIPIENT@example.com  ', 'Jane Doe', 'John Doe', 'Enjoy');
    $second = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', 'Enjoy');

    expect($first)->toBe($second);
});

test('trims whitespace on names', function () {
    $first = PayloadFingerprint::giftCard(5000, 'recipient@example.com', '  Jane Doe  ', '  John Doe  ', 'Enjoy');
    $second = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', 'Enjoy');

    expect($first)->toBe($second);
});

test('null and empty string message are equivalent', function () {
    $first = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', null);
    $second = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', '');

    expect($first)->toBe($second);
});

test('trims message before hashing', function () {
    $first = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', '  Enjoy your gift card  ');
    $second = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', 'Enjoy your gift card');

    expect($first)->toBe($second);
});

test('preserves name casing in hash', function () {
    $first = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'Jane Doe', 'John Doe', 'Enjoy');
    $second = PayloadFingerprint::giftCard(5000, 'recipient@example.com', 'jane Doe', 'John Doe', 'Enjoy');

    expect($first)->not->toBe($second);
});
