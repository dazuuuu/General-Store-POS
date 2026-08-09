<?php
// app/models/SubscriptionModel.php
namespace Models;

/**
 * Subscription state per tenant. Not auto-scoped because it's created/activated
 * during registration (before any tenant context exists) and read by the
 * platform during billing; callers pass the tenant id explicitly.
 */
class SubscriptionModel extends Model
{
    protected string $table = 'subscriptions';
    protected bool $tenantScoped = false;

    public function createForTenant(int $tenantId, int $planId, string $interval, float $amount): int
    {
        return $this->insert([
            'tenant_id'        => $tenantId,
            'plan_id'          => $planId,
            'billing_interval' => $interval,
            'amount'           => $amount,
            'status'           => 'trialing',
        ]);
    }

    public function forTenant(int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM subscriptions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetch() ?: null;
    }

    /** Start (or renew) the active period. */
    public function activatePeriod(int $subscriptionId, string $start, string $end): bool
    {
        return $this->update($subscriptionId, [
            'status'               => 'active',
            'current_period_start' => $start,
            'current_period_end'   => $end,
        ]);
    }
}