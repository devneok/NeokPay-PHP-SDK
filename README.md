# NEOK Pay PHP SDK

Framework-independent PHP ^8.3 client for NEOK Pay Merchant API v1. It has no Laravel or Stellar dependency.

**Controlled beta candidate: 1.0.0-beta.2. Not tagged or published yet.** Use only the approved beta distribution. Do not start live payments merely to try the SDK; see [BETA-PLAN.md](BETA-PLAN.md).

## Installation

```bash
composer require "devneok/neokpay-php:1.0.0-beta.2"
```

This command becomes usable once the beta is tagged and available in your registry. It was solver-tested against local simulated beta metadata, not an existing public release. To opt into later compatible betas, use `"devneok/neokpay-php:^1.0.0-beta.2@beta"`.

The SDK User-Agent reports the installed Composer version, including beta/development versions. Without usable metadata it reports `neokpay-php/development`, never an assumed stable release. An optional `userAgentSuffix` remains available in `ClientConfig`.

Provide any PSR-18 client and PSR-17 request/stream factories. For a complete Guzzle example:

```bash
composer require "guzzlehttp/guzzle:^7.0" "guzzlehttp/psr7:^2.0"
```

```php
use Neok\Pay\Client;
use Neok\Pay\ClientConfig;

$factory = new \GuzzleHttp\Psr7\HttpFactory();
$client = new Client(
    new ClientConfig(apiKey: (string) getenv('NEOKPAY_API_KEY')),
    new \GuzzleHttp\Client(['timeout' => 15, 'connect_timeout' => 5]),
    $factory,
    $factory,
);
```

## Create a checkout

```php
use Neok\Pay\DTO\CreateCheckoutRequest;

$checkout = $client->checkouts()->create(
    new CreateCheckoutRequest(
        reference: 'ORDER-1001', amount: '25.00', currency: 'USD',
        details: 'Example order', webhookUrl: 'https://merchant.test/neokpay/webhook',
        successUrl: 'https://merchant.test/payment/success',
        cancelUrl: 'https://merchant.test/payment/cancel',
        customerName: 'Ada Lovelace', customerEmail: 'ada@example.test',
    ),
    idempotencyKey: 'ORDER-1001',
);

header('Location: '.$checkout->checkoutUrl);
```

Use a durable, unique idempotency key. Reusing it with the same request returns the original checkout; using it with changed input raises `ConflictException`.

## Retrieve payment state

**A browser success redirect is NOT proof of payment.** Fulfill only after a verified signed webhook or authenticated server-to-server retrieval. Match the stored payment ID, order reference, expected amount/currency and paid status; make fulfillment idempotent.

```php
$payment = $client->payments()->retrieve($checkout->payment->id);
if ($payment->isPaid()) {
    // Fulfil only after server-confirmed paid state or a verified webhook.
}
```

Amounts are decimal strings and must not be converted to floats.

## Verify webhooks

```php
use Neok\Pay\Webhooks\WebhookVerifier;

$event = (new WebhookVerifier())->verify(
    rawBody: file_get_contents('php://input'),
    signatureHeader: $_SERVER['HTTP_X_NEOKPAY_SIGNATURE'] ?? '',
    secret: (string) getenv('NEOKPAY_WEBHOOK_SECRET'),
);
```

The verifier checks the raw body, HMAC-SHA256 signature, and a five-minute timestamp window. Persist processed event IDs yourself for durable duplicate-event protection. It accepts unknown future event types safely; only `payment.succeeded` is currently emitted.

## Errors and security

`AuthenticationException`, `ValidationException`, `NotFoundException`, `ConflictException`, `RateLimitException`, `ApiException`, and `TransportException` provide predictable failure handling. Secrets are never placed in URLs or exception messages and are redacted from config debugging.

```php
try {
    $payment = $client->payments()->retrieve($storedPaymentId);
} catch (\Neok\Pay\Exceptions\AuthenticationException $e) {
    // Check server-issued credentials privately; never print/log them.
} catch (\Neok\Pay\Exceptions\RateLimitException $e) {
    // Back off before retrying.
} catch (\Neok\Pay\Exceptions\TransportException $e) {
    // Retry safely; keep the same idempotency key for checkout retries.
}
```

Keep credentials out of source control, URLs and logs. Do not dump or serialize secret-bearing objects. Transport timeouts are configured on your PSR client, not through non-standard SDK options. See [SECURITY.md](SECURITY.md) for private reporting.

The production API base URL defaults to `https://pay.neok.me/api/v1`. A public sandbox credential/base-URL strategy is not yet defined server-side, so configure an alternate verified base URL only when NEOK Pay provides one.

Canonical API documentation recommendation: `https://pay.neok.me/api/documentation`, the existing server documentation route. Its current page documents the legacy API; it must be updated with API v1 before being advertised as v1 documentation. No new domain or documentation deployment is assumed.

Beta release steps and rollback guidance: [RELEASE-CHECKLIST.md](RELEASE-CHECKLIST.md). Initial features: [CHANGELOG.md](CHANGELOG.md). CI runs PHPUnit, PHPStan and Composer validation on PHP 8.3 and 8.4 with no payment credentials.
