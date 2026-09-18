<?php

declare(strict_types=1);

namespace Neok\Pay\Webhooks;

use DateTimeImmutable;
use Neok\Pay\DTO\WebhookEvent;
use Neok\Pay\Exceptions\WebhookVerificationException;
use Neok\Pay\Http\ResponseParser;

final readonly class WebhookVerifier
{
    public function __construct(private int $toleranceSeconds = 300, private ResponseParser $parser = new ResponseParser)
    {
        if ($toleranceSeconds < 0) {
            throw new \InvalidArgumentException('Tolerance must not be negative.');
        }
    }

    public function verify(string $rawBody, string $signatureHeader, #[\SensitiveParameter] string $secret, ?int $now = null): WebhookEvent
    {
        if ($secret === '') {
            throw new WebhookVerificationException('Webhook secret is required.');
        }
        if (! preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $signatureHeader, $m)) {
            throw new WebhookVerificationException('Malformed webhook signature.');
        }
        $now ??= time();
        if (abs($now - (int) $m[1]) > $this->toleranceSeconds) {
            throw new WebhookVerificationException('Webhook timestamp is outside the allowed tolerance.');
        }
        if (! hash_equals(hash_hmac('sha256', $m[1].'.'.$rawBody, $secret), $m[2])) {
            throw new WebhookVerificationException('Webhook signature is invalid.');
        }
        try {
            $value = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WebhookVerificationException('Webhook body is malformed.');
        }
        if (! is_array($value) || ! is_string($value['id'] ?? null) || ! is_string($value['type'] ?? null) || ! is_string($value['created_at'] ?? null) || ! is_array($value['data'] ?? null)) {
            throw new WebhookVerificationException('Webhook payload is invalid.');
        }
        try {
            $created = new DateTimeImmutable($value['created_at']);
        } catch (\Exception) {
            throw new WebhookVerificationException('Webhook payload is invalid.');
        }
        $payment = null;
        if (is_array($value['data']['payment'] ?? null)) {
            try {
                $payment = $this->parser->payment($value['data']['payment']);
            } catch (\Throwable) {
                if ($value['type'] === 'payment.succeeded') {
                    throw new WebhookVerificationException('Webhook payload is invalid.');
                }
            }
        }
        if ($value['type'] === 'payment.succeeded' && ($payment === null || ! $payment->isPaid())) {
            throw new WebhookVerificationException('Webhook payload is invalid.');
        }

        return new WebhookEvent($value['id'], $value['type'], $created, $value['data'], $payment);
    }
}
