<?php

declare(strict_types=1);

namespace Neok\Pay\DTO;

final readonly class Checkout { public function __construct(public Payment $payment, public string $checkoutUrl) {} }
