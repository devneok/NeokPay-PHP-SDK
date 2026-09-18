<?php

declare(strict_types=1);

namespace Neok\Pay\DTO;

final readonly class CreateCheckoutRequest
{
    public function __construct(public string $reference, public string $amount, public string $currency, public string $details, public string $webhookUrl, public string $successUrl, public string $cancelUrl, public string $customerName, public string $customerEmail, public ?string $siteLogo = null, public ?string $checkoutTheme = null) {}
    /** @return array<string, string> */
    public function toArray(): array { return array_filter(['reference' => $this->reference, 'amount' => $this->amount, 'currency' => $this->currency, 'details' => $this->details, 'webhook_url' => $this->webhookUrl, 'success_url' => $this->successUrl, 'cancel_url' => $this->cancelUrl, 'customer_name' => $this->customerName, 'customer_email' => $this->customerEmail, 'site_logo' => $this->siteLogo, 'checkout_theme' => $this->checkoutTheme], static fn (?string $value): bool => $value !== null); }
}
