<?php

namespace App\Http\Services;

use App\Models\Lab;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\StripeClient;

class StripeConnectService
{
    public function createConnectedAccountForLab(Lab $lab): array
    {
        if ($lab->stripe_account_id) {
            return $this->createSuccessResponse(
                'Lab already has a connected account',
                ['stripe_account_id' => $lab->stripe_account_id]
            );
        }

        $this->validateLabForStripe($lab);

        $stripeAccountResponse = $this->createStripeAccount($lab);

        if (! ($stripeAccountResponse['success'] ?? false)) {
            Log::error('Stripe account creation failed', [
                'lab_id' => $lab->id,
                'error' => $stripeAccountResponse['message'],
                'trace' => $stripeAccountResponse['trace'] ?? null,
            ]);

            return $stripeAccountResponse;
        }

        $accountId = $stripeAccountResponse['stripe_account_id'];

        $lab->stripe_account_id = $accountId;
        $lab->save();

        $lab->refresh();

        return $this->createSuccessResponse(
            'Connected account created successfully',
            ['stripe_account_id' => $accountId]
        );
    }

    public function createAccountLink(Lab $lab, string $returnUrl, string $refreshUrl): array
    {
        $this->validateAccountExists($lab);
        $this->validateUrls($returnUrl, $refreshUrl);

        $accountId = $lab->stripe_account_id;
        $isOnboarded = $this->isAccountOnboarded($accountId);

        if ($isOnboarded) {
            return $this->createErrorResponse('Lab is already fully onboarded');
        }

        $accountLinkResponse = $this->createAccountLinkViaStripe($accountId, $returnUrl, $refreshUrl);

        if (! ($accountLinkResponse['success'] ?? false)) {
            Log::error('Account link creation failed', [
                'lab_id' => $lab->id,
                'account_id' => $accountId,
                'error' => $accountLinkResponse['message'],
                'return_url' => $returnUrl,
                'refresh_url' => $refreshUrl,
            ]);

            return $accountLinkResponse;
        }

        return $accountLinkResponse;
    }

    public function isAccountOnboarded(string $accountId): bool
    {
        if (empty($accountId)) {
            return false;
        }

        $response = $this->getAccountDetails($accountId);

        if (! ($response['success'] ?? false)) {
            Log::error('Failed to check account onboarding status', [
                'account_id' => $accountId,
                'error' => $response['message'] ?? 'Unknown error',
            ]);

            return false;
        }

        $account = $response['account'] ?? [];

        $requiredFields = ['details_submitted', 'charges_enabled', 'payouts_enabled'];

        foreach ($requiredFields as $field) {
            if (! data_get($account, $field, false)) {
                return false;
            }
        }

        return true;
    }

    public function canProcessPayments(string $accountId): bool
    {
        if (empty($accountId)) {
            return false;
        }

        return $this->isAccountOnboarded($accountId);
    }

    private function validateLabForStripe(Lab $lab): void
    {
        $requiredFields = [
            'name' => 'Lab name',
            'phone' => 'Lab phone',
            'address' => 'Lab address',
        ];

        $missing = [];
        foreach ($requiredFields as $field => $label) {
            if (empty($lab->{$field})) {
                $missing[] = $label;
            }
        }

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'lab' => ['Complete lab information is required for Stripe onboarding. Missing: '.implode(', ', $missing)],
            ]);
        }
    }

    private function validateAccountExists(Lab $lab): void
    {
        if (empty($lab->stripe_account_id)) {
            throw ValidationException::withMessages([
                'stripe_account_id' => ['Lab does not have a connected Stripe account'],
            ]);
        }
    }

    private function validateUrls(string $returnUrl, string $refreshUrl): void
    {
        if (empty($returnUrl) || ! filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'return_url' => ['The return URL must be a valid URL.'],
            ]);
        }

        if (empty($refreshUrl) || ! filter_var($refreshUrl, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'refresh_url' => ['The refresh URL must be a valid URL.'],
            ]);
        }
    }

    private function createSuccessResponse(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    private function createErrorResponse(string $message, array $errorDetails = [], ?string $trace = null): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error_details' => $errorDetails,
            'trace' => $trace,
        ];
    }

    private function createStripeAccount(Lab $lab): array
    {
        try {
            $stripe = $this->getStripeClient();

            $businessProfile = [
                'name' => $lab->name,
            ];

            if (! empty($lab->website_url) && filter_var($lab->website_url, FILTER_VALIDATE_URL)) {
                $businessProfile['url'] = $lab->website_url;
            }

            $response = $this->withoutStripeWarnings(fn () => $stripe->accounts->create([
                'type' => 'express',
                'country' => 'US',
                'email' => $lab->email ?? 'admin@lab.com',
                'business_profile' => $businessProfile,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]));

            return ['success' => true, 'stripe_account_id' => $response->id];
        } catch (\Throwable $e) {
            return $this->createErrorResponse(
                'Failed to create Stripe connected account',
                ['exception' => $e->getMessage()],
                $e->getTraceAsString()
            );
        }
    }

    private function createAccountLinkViaStripe(string $accountId, string $returnUrl, string $refreshUrl): array
    {
        try {
            $stripe = $this->getStripeClient();

            $response = $this->withoutStripeWarnings(fn () => $stripe->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
                'collect' => 'eventually_due',
            ]));

            return ['success' => true, 'url' => $response->url];
        } catch (\Throwable $e) {
            $params = [
                'account_id' => $accountId,
                'return_url' => $returnUrl,
                'refresh_url' => $refreshUrl,
                'exception' => $e->getMessage(),
            ];

            Log::error('Stripe account link creation failed', $params);

            return $this->createErrorResponse(
                'Failed to create account link',
                $params,
                $e->getTraceAsString()
            );
        }
    }

    private function getAccountDetails(string $accountId): array
    {
        try {
            $stripe = $this->getStripeClient();
            $account = $this->withoutStripeWarnings(fn () => $stripe->accounts->retrieve($accountId));

            return ['success' => true, 'account' => $account->toArray()];
        } catch (\Throwable $e) {
            $params = [
                'account_id' => $accountId,
                'exception' => $e->getMessage(),
            ];

            Log::error('Failed to retrieve Stripe account details', $params);

            return $this->createErrorResponse(
                'Failed to retrieve account details',
                $params,
                $e->getTraceAsString()
            );
        }
    }

    private function getStripeClient(): StripeClient
    {
        $config = config('stripe-connect.connect');

        if ($config['test_mode']) {
            return new StripeClient($config['test_secret_key']);
        }

        $message = 'Stripe Connect is only configured for test mode.';
        Log::critical('Stripe test mode is required', ['message' => $message]);

        throw new \RuntimeException($message);
    }

    /**
     * Run a callback while suppressing E_USER_WARNING emitted by the Stripe SDK
     * for deprecation notices (e.g. Accounts v2 migration notices).
     */
    private function withoutStripeWarnings(callable $callback): mixed
    {
        $previousHandler = set_error_handler(static function () {
            // Silently swallow E_USER_WARNING from the Stripe SDK.
        }, E_USER_WARNING);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
