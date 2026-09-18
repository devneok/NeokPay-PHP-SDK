<?php

declare(strict_types=1);

namespace Neok\Pay\Tests;

use Neok\Pay\Exceptions\WebhookVerificationException;
use Neok\Pay\Webhooks\WebhookVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookVerifierTest extends TestCase
{
    private const NOW = 1800000000;

    private const SECRET = 'fake-webhook-secret';

    private function body(string $type = 'payment.succeeded'): string
    {
        return json_encode([
            'id' => 'evt_contract_fixture',
            'type' => $type,
            'created_at' => '2026-09-18T00:00:00+00:00',
            'data' => $type === 'payment.succeeded'
                ? ['payment' => array_replace(ClientTest::payment(), ['status' => 'paid'])]
                : ['future_field' => 'value'],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    private function sign(string $body, int $time = self::NOW): string
    {
        return 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, self::SECRET);
    }

    public function test_verified_success_retains_event_and_payment_contract(): void
    {
        $body = $this->body();
        $event = (new WebhookVerifier)->verify($body, $this->sign($body), self::SECRET, self::NOW);
        self::assertSame('evt_contract_fixture', $event->id);
        self::assertTrue($event->isPaymentSucceeded());
        self::assertSame('trx_1', $event->payment->id);
        self::assertSame('ORDER-1', $event->payment->reference);
        self::assertSame('25.00000000', $event->payment->amount);
        self::assertSame('USD', $event->payment->currency);
        self::assertTrue($event->payment->isPaid());
    }

    public function test_unknown_verified_event_is_forward_compatible(): void
    {
        $body = $this->body('future.event');
        $event = (new WebhookVerifier)->verify($body, $this->sign($body), self::SECRET, self::NOW);
        self::assertSame('future.event', $event->type);
        self::assertNull($event->payment);
        self::assertSame(['future_field' => 'value'], $event->data);
    }

    #[DataProvider('invalidHeaders')]
    public function test_invalid_headers_are_rejected_without_secret_leakage(string $header): void
    {
        try {
            (new WebhookVerifier)->verify($this->body(), $header, self::SECRET, self::NOW);
            self::fail('Expected verification failure.');
        } catch (WebhookVerificationException $exception) {
            self::assertStringNotContainsString(self::SECRET, (string) $exception);
        }
    }

    public static function invalidHeaders(): array
    {
        return [
            [''], ['malformed'], ['t=1800000000'], ['v1='.str_repeat('a', 64)],
            ['t=invalid,v1='.str_repeat('a', 64)],
            ['t=1800000000,v1='.str_repeat('a', 64)],
        ];
    }

    #[DataProvider('outsideTolerance')]
    public function test_timestamps_outside_window_are_rejected(int $offset): void
    {
        $body = $this->body();
        $this->expectException(WebhookVerificationException::class);
        (new WebhookVerifier)->verify($body, $this->sign($body, self::NOW + $offset), self::SECRET, self::NOW);
    }

    public static function outsideTolerance(): array
    {
        return [[-301], [301]];
    }

    #[DataProvider('invalidBodies')]
    public function test_correct_hmac_does_not_make_malformed_payload_valid(string $body): void
    {
        $this->expectException(WebhookVerificationException::class);
        (new WebhookVerifier)->verify($body, $this->sign($body), self::SECRET, self::NOW);
    }

    public static function invalidBodies(): array
    {
        return [
            ['{'], ['null'], ['{}'],
            ['{"id":"evt_bad","type":"payment.succeeded","created_at":"2026-09-18T00:00:00+00:00","data":{}}'],
        ];
    }

    public function test_signature_is_over_exact_raw_bytes(): void
    {
        $body = $this->body();
        $changedWhitespace = json_encode(json_decode($body, true), JSON_THROW_ON_ERROR);
        $this->expectException(WebhookVerificationException::class);
        (new WebhookVerifier)->verify($changedWhitespace, $this->sign($body), self::SECRET, self::NOW);
    }

    public function test_missing_secret_is_rejected(): void
    {
        $body = $this->body();
        $this->expectException(WebhookVerificationException::class);
        (new WebhookVerifier)->verify($body, $this->sign($body), '', self::NOW);
    }

    public function test_secret_is_redacted_even_when_exception_arguments_are_enabled(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            (new WebhookVerifier)->verify('{}', 'invalid', self::SECRET, self::NOW);
            self::fail('Expected verification failure.');
        } catch (WebhookVerificationException $exception) {
            self::assertStringNotContainsString(self::SECRET, var_export($exception->getTrace(), true));
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }
}
