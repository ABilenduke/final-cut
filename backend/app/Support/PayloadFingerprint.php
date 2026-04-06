<?php

namespace App\Support;

final class PayloadFingerprint
{
    public static function giftCard(
        int $amount,
        string $recipientEmail,
        string $recipientName,
        string $senderName,
        ?string $message
    ): string {
        $payload = [
            'amount' => $amount,
            'recipientEmail' => strtolower(trim($recipientEmail)),
            'recipientName' => trim($recipientName),
            'senderName' => trim($senderName),
            'message' => self::normalizeMessage($message),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function normalizeMessage(?string $message): string
    {
        return trim($message ?? '');
    }
}
