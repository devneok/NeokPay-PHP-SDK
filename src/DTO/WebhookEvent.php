<?php

declare(strict_types=1);

namespace Neok\Pay\DTO;

use DateTimeImmutable;

final readonly class WebhookEvent
{
    /** @param array<string, mixed> $data */
    public function __construct(public string $id, public string $type, public DateTimeImmutable $createdAt, public array $data, public ?Payment $payment) {}
    public function isPaymentSucceeded(): bool { return $this->type === 'payment.succeeded'; }
}
