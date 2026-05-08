<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class PayloadFingerprint
{
    public static function giftCard(
        int $amount,
        string $recipientEmail,
        string $recipientName,
        string $senderName,
        ?string $message,
        string $edition = 'reactor',
        string $deliveryMethod = 'email',
        ?string $scheduledSendAt = null,
    ): string {
        $payload = [
            'amount' => $amount,
            'recipientEmail' => strtolower(trim($recipientEmail)),
            'recipientName' => trim($recipientName),
            'senderName' => trim($senderName),
            'message' => self::normalizeMessage($message),
            'edition' => $edition,
            'deliveryMethod' => $deliveryMethod,
            // Normalize to a canonical UTC ISO-8601 string so retries that
            // re-serialize the same instant in different formats (with vs.
            // without milliseconds, with vs. without TZ) hash identically.
            'scheduledSendAt' => self::normalizeTimestamp($scheduledSendAt),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function normalizeMessage(?string $message): string
    {
        return trim($message ?? '');
    }

    private static function normalizeTimestamp(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->utc()->toIso8601ZuluString();
    }
}
