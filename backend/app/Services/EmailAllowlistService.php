<?php

namespace App\Services;

use App\Models\AllowedEmailEntry;

class EmailAllowlistService
{
    public function __construct(private readonly AppSettingService $settings) {}

    public function isAllowed(string $email): bool
    {
        if ($this->allowAll()) {
            return true;
        }

        $email = strtolower(trim($email));
        $patterns = $this->patterns();

        if ($patterns === []) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($email, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function allowAll(): bool
    {
        if ($this->settings->getBool('allow_all_emails')) {
            return true;
        }

        return (bool) config('flc.allow_all_emails', false);
    }

    /**
     * @return list<string>
     */
    public function patterns(): array
    {
        $fromDb = AllowedEmailEntry::query()
            ->where('is_active', true)
            ->pluck('pattern')
            ->all();

        $fromEnv = config('flc.allowed_emails', []);

        return array_values(array_unique(array_merge($fromDb, $fromEnv)));
    }

    private function matchesPattern(string $email, string $pattern): bool
    {
        $pattern = strtolower(trim($pattern));

        if ($pattern === $email) {
            return true;
        }

        if (str_starts_with($pattern, '*@')) {
            $domain = substr($pattern, 2);

            return $domain !== '' && str_ends_with($email, '@'.$domain);
        }

        return false;
    }
}
