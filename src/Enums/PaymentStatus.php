<?php

declare(strict_types=1);

namespace Neok\Pay\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending'; case Paid = 'paid'; case Cancelled = 'cancelled';
    public function isTerminal(): bool { return $this !== self::Pending; }
}
