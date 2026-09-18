<?php

declare(strict_types=1);

namespace Neok\Pay\Tests;

use Neok\Pay\Client;
use Neok\Pay\ClientConfig;
use Neok\Pay\DTO\CreateCheckoutRequest;
use Neok\Pay\Enums\PaymentStatus;
use Neok\Pay\Exceptions\ApiException;
use Neok\Pay\Exceptions\AuthenticationException;
use Neok\Pay\Exceptions\ConflictException;
use Neok\Pay\Exceptions\NotFoundException;
use Neok\Pay\Exceptions\RateLimitException;
use Neok\Pay\Exceptions\TransportException;
use Neok\Pay\Exceptions\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class ClientTest extends TestCase
{
    private function client(Response|ClientExceptionInterface $result, ?RequestInterface &$seen = null): Client
    {
        $http = $this->createMock(ClientInterface::class);
        $http->method('sendRequest')->willReturnCallback(
            static function (RequestInterface $request) use ($result, &$seen) {
                $seen = $request;
                if ($result instanceof ClientExceptionInterface) {
                    throw $result;
                }

                return $result;
            }
        );
        $factory = new Psr17Factory;

        return new Client(new ClientConfig('fake-api-key'), $http, $factory, $factory);
    }

    public static function payment(): array
    {
        return [
            'id' => 'trx_1',
            'reference' => 'ORDER-1',
            'amount' => '25.00000000',
            'currency' => 'USD',
            'status' => 'pending',
            'created_at' => '2026-09-18T00:00:00+00:00',
            'paid_at' => null,
        ];
    }

    private function request(): CreateCheckoutRequest
    {
        return new CreateCheckoutRequest(
            reference: 'ORDER-1',
            amount: '25.00000000',
            currency: 'USD',
            details: 'Order',
            webhookUrl: 'https://merchant.test/webhook',
            successUrl: 'https://merchant.test/success',
            cancelUrl: 'https://merchant.test/cancel',
            customerName: 'Ada',
            customerEmail: 'ada@example.test',
        );
    }

    public function test_checkout_preserves_contract_and_sends_idempotency(): void
    {
        $data = self::payment() + ['checkout_url' => 'https://pay.test/checkout'];
        $client = $this->client(new Response(201, [], json_encode(['data' => $data])), $seen);
        $checkout = $client->checkouts()->create($this->request(), 'order-1');
        self::assertSame('25.00000000', $checkout->payment->amount);
        self::assertSame('USD', $checkout->payment->currency);
        self::assertSame('ORDER-1', $checkout->payment->reference);
        self::assertSame('https://pay.test/checkout', $checkout->checkoutUrl);
        self::assertSame('POST', $seen->getMethod());
        self::assertSame('https://pay.neok.me/api/v1/checkouts', (string) $seen->getUri());
        self::assertSame('Bearer fake-api-key', $seen->getHeaderLine('Authorization'));
        self::assertSame('order-1', $seen->getHeaderLine('Idempotency-Key'));
        self::assertSame('application/json', $seen->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('neokpay-php/', $seen->getHeaderLine('User-Agent'));
        self::assertSame((new ClientConfig('fake-api-key'))->userAgent(), $seen->getHeaderLine('User-Agent'));
        self::assertSame($this->request()->toArray(), json_decode((string) $seen->getBody(), true));
    }

    #[DataProvider('statuses')]
    public function test_payment_retrieval_preserves_typed_contract(string $status): void
    {
        $data = array_replace(self::payment(), ['status' => $status]);
        $client = $this->client(new Response(200, [], json_encode(['data' => $data])), $seen);
        $payment = $client->payments()->retrieve('trx_1');
        self::assertSame('trx_1', $payment->id);
        self::assertSame('25.00000000', $payment->amount);
        self::assertSame('USD', $payment->currency);
        self::assertSame(PaymentStatus::from($status), $payment->status);
        self::assertSame($status === 'paid', $payment->isPaid());
        self::assertSame('2026-09-18T00:00:00+00:00', $payment->createdAt->format(DATE_ATOM));
        self::assertSame('GET', $seen->getMethod());
        self::assertFalse($seen->hasHeader('Idempotency-Key'));
    }

    public static function statuses(): array
    {
        return [['pending'], ['paid'], ['cancelled']];
    }

    #[DataProvider('errors')]
    public function test_http_errors_map_to_safe_exceptions(int $status, string $class, string $code): void
    {
        $client = $this->client(new Response($status, [], json_encode([
            'error' => ['code' => $code, 'message' => 'Request failed.'],
        ])));
        try {
            $client->payments()->retrieve('trx_1');
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertInstanceOf($class, $exception);
            self::assertSame($status, $exception->statusCode);
            self::assertSame($code, $exception->apiCode);
            self::assertStringNotContainsString('fake-api-key', (string) $exception);
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
        }
    }

    public static function errors(): array
    {
        return [
            [400, ApiException::class, 'validation_error'],
            [401, AuthenticationException::class, 'authentication_failed'],
            [403, ApiException::class, 'unknown_error'],
            [404, NotFoundException::class, 'payment_not_found'],
            [409, ConflictException::class, 'idempotency_conflict'],
            [422, ValidationException::class, 'validation_error'],
            [429, RateLimitException::class, 'rate_limit_exceeded'],
            [500, ApiException::class, 'internal_error'],
            [502, ApiException::class, 'upstream_error'],
            [503, ApiException::class, 'upstream_error'],
        ];
    }

    public function test_checkout_validation_retains_field_errors(): void
    {
        $errors = ['amount' => ['The amount must be positive.']];
        $client = $this->client(new Response(422, [], json_encode([
            'error' => ['code' => 'validation_error', 'message' => 'Invalid request.', 'details' => $errors],
        ])));
        try {
            $client->checkouts()->create($this->request(), 'order-1');
            self::fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            self::assertSame($errors, $exception->errors());
        }
    }

    public function test_checkout_conflict_is_typed(): void
    {
        $client = $this->client(new Response(409, [], '{"error":{"code":"idempotency_conflict","message":"Conflict"}}'));
        $this->expectException(ConflictException::class);
        $client->checkouts()->create($this->request(), 'order-1');
    }

    #[DataProvider('malformedResponses')]
    public function test_malformed_payment_responses_fail_closed(string $body): void
    {
        $this->expectException(ApiException::class);
        $this->client(new Response(200, [], $body))->payments()->retrieve('trx_1');
    }

    public static function malformedResponses(): array
    {
        return [
            [''], ['{'], ['null'], ['42'], ['[]'], ['{}'], ['{"data":[]}'],
            [json_encode(['data' => array_replace(self::payment(), ['status' => 'future'])])],
            [json_encode(['data' => array_replace(self::payment(), ['amount' => 25.0])])],
            [json_encode(['data' => array_replace(self::payment(), ['created_at' => 'not-a-date'])])],
        ];
    }

    #[DataProvider('transportFailures')]
    public function test_transport_failure_does_not_leak_headers(string $message): void
    {
        $failure = new class($message) extends \RuntimeException implements ClientExceptionInterface {};
        try {
            $this->client($failure)->payments()->retrieve('trx_1');
            self::fail('Expected transport exception.');
        } catch (TransportException $exception) {
            self::assertStringNotContainsString('fake-api-key', (string) $exception);
            self::assertStringNotContainsString('Authorization', (string) $exception);
        }
    }

    public static function transportFailures(): array
    {
        return [
            ['Network failure'],
            ['Timeout while sending Authorization: Bearer fake-api-key'],
        ];
    }

    public function test_config_debug_redacts_api_key(): void
    {
        ob_start();
        var_dump(new ClientConfig('fake-api-key'));
        $output = ob_get_clean();
        self::assertStringNotContainsString('fake-api-key', $output);
        self::assertStringContainsString('[REDACTED]', $output);
    }

    public function test_invalid_config_exception_trace_redacts_api_key(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            new ClientConfig('fake-api-key', 'invalid');
            self::fail('Expected invalid config.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringNotContainsString('fake-api-key', var_export($exception->getTrace(), true));
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }
}
