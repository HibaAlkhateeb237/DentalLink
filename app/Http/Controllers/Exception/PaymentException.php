<?php

namespace App\Http\Controllers\Exception;

class PaymentException extends \Exception
{
    public static function unauthorizedPayment(): self
    {
        return new self('You are not authorized to make this payment.');
    }

    public static function labNotFound(int $labId): self
    {
        return new self("Lab with ID {$labId} not found.");
    }

    public static function labNotConnected(): self
    {
        return new self('Lab is not connected to Stripe. Please contact the system administrator.');
    }

    public static function labNotOnboarded(): self
    {
        return new self('Lab has not completed Stripe onboarding. Please contact the lab owner.');
    }

    public static function alreadyPaid(int $orderId): self
    {
        return new self("Order #{$orderId} is already paid.");
    }

    public static function alreadyRefunded(int $orderId): self
    {
        return new self("Order #{$orderId} has already been refunded.");
    }

    public static function checkoutSessionFailed(string $message): self
    {
        return new self("Failed to create checkout session: {$message}");
    }
}
