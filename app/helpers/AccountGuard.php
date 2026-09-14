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
        $tenantId=(int)($user['tenant_id']??0);
        if($tenantId>0){
            $st=Database::pdo()->prepare('SELECT status FROM tenants WHERE id=? LIMIT 1');$st->execute([$tenantId]);
            $status=(string)$st->fetchColumn();
            if($status!=='active')return ['ok'=>false,'reason'=>$status==='suspended'?'tenant_suspended':'tenant_closed'];
        }

        return ['ok' => true, 'reason' => null];
    }

    public static function message(string $reason): string
    {
        return [
            'not_activated' => 'Please activate your account using the link we emailed you.',
            'tenant_suspended' => 'This business account is closed. Please contact developer support.',
            'tenant_closed' => 'This business account is unavailable. Please contact developer support.',
        ][$reason] ?? 'Your account cannot be accessed right now. Please contact support.';
    }
}