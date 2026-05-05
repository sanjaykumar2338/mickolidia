<?php

namespace App\Support;

class CountryEligibility
{
    /**
     * @return array<string, string>
     */
    public function allCountries(): array
    {
        return (array) config('wolforix.countries', []);
    }

    /**
     * @return list<string>
     */
    public function restrictedCountryCodes(): array
    {
        return array_values(array_map(
            static fn (mixed $code): string => strtoupper((string) $code),
            (array) config('wolforix.restricted_countries', []),
        ));
    }

    /**
     * @return array<string, string>
     */
    public function allowedCountries(): array
    {
        return (array) config('wolforix.checkout_countries', []);
    }

    public function normalizeCountryCode(mixed $countryCode): string
    {
        return strtoupper(trim((string) $countryCode));
    }

    public function isAllowed(mixed $countryCode): bool
    {
        return array_key_exists($this->normalizeCountryCode($countryCode), $this->allowedCountries());
    }

    public function countryName(mixed $countryCode): string
    {
        $countryCode = $this->normalizeCountryCode($countryCode);

        return $this->allCountries()[$countryCode] ?? $countryCode;
    }
}
