<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmailDomain implements ValidationRule
{
    /**
     * List of known common typo domains to reject
     *
     * @var array<string>
     */
    private const REJECTED_DOMAINS = [
        'gmail.co',
        'gmail.con',
        'gmial.com',
        'yahoo.co',
        'outlook.co',
        'hotmail.co',
    ];

    /**
     * Run the validation rule.
     *
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return;
        }

        $domain = substr($value, strrpos($value, '@') + 1);

        if (in_array(strtolower($domain), self::REJECTED_DOMAINS, true)) {
            $fail('The :attribute must not use the domain ' . $domain . '.');
        }
    }
}
