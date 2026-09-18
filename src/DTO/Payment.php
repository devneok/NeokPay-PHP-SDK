<?php

declare(strict_types=1);

namespace Neok\Pay\DTO;

use DateTimeImmutable;
use Neok\Pay\Enums\PaymentStatus;

final readonly class Payment
{
    public function __construct(public string $id, public string $reference, public string $amount, public string $currency, public PaymentStatus $status, public DateTimeImmutable $createdAt, public ?DateTimeImmutable $paidAt) {}
    public function isPaid(): bool { return $this->status === PaymentStatus::Paid; }
}
