<?php
// app/helpers/AccountGuard.php
// Decides whether an account may log in / use the app, based solely on
// activation state. Subscription logic has been removed (single-tenant POS).

class AccountGuard
{
    /**
     * @param array $user  the users row (needs is_active, email_verified)
     * @return array ['ok'=>bool, 'reason'=>?string]
     */
    public static function evaluate(array $user): array
    {
        if (empty($user['is_active']) || empty($user['email_verified'])) {
            return ['ok' => false, 'reason' => 'not_activated'];
        }

        return ['ok' => true, 'reason' => null];
    }

    public static function message(string $reason): string
    {
        return [
            'not_activated' => 'Please activate your account using the link we emailed you.',
        ][$reason] ?? 'Your account cannot be accessed right now. Please contact support.';
    }
}