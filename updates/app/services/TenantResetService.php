<?php

class TenantResetService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
    }

    public const GROUPS = [
        'sales' => 'Sales, orders and customer invoices',
        'stock' => 'Products, stock and store invoices',
        'customers' => 'Customers and loyalty',
        'carts' => 'Carts, held orders and store orders',
        'staff_time' => 'Staff clock-in records',
        'activity' => 'Activity logs, notifications and sync events',
    ];

    public function resetShopData(int $tenantId, ?array $groups = null): array
    {
        if ($tenantId <= 0) {
            return ['ok' => false, 'error' => 'Tenant not found.', 'deleted' => []];
        }

        $groupTables = [
            'sales' => [
                'invoice_items',
                'invoices',
                'order_payments',
                'order_items',
                'orders',
                'sale_payments',
                'sale_items',
                'sales',
                'product_returns',
            ],
            'stock' => [
                'store_invoice_items',
                'store_invoices',
                'store_products',
                'product_price_tiers',
                'stock_intake_items',
                'stock_intakes',
                'products',
                'subcategories',
                'categories',
                'product_categories',
                'book_attributes',
                'suppliers',
                'store_categories',
            ],
            'customers' => [
                'loyalty_transactions',
                'customers',
            ],
            'carts' => [
                'held_order_items',
                'held_orders',
                'cart_items',
                'saved_for_later',
                'cart_sessions',
                'store_order_items',
                'store_orders',
                'store_cart',
                'store_saved_for_later',
            ],
            'staff_time' => [
                'staff_reclock_authorizations',
                'staff_time_logs',
            ],
            'activity' => [
                'sync_events',
                'tenant_notifications',
                'audit_log',
            ],
        ];
        $requested = $groups === null
            ? array_keys($groupTables)
            : array_values(array_intersect(array_keys($groupTables), array_map('strval', $groups)));
        if (!$requested) {
            return ['ok' => false, 'error' => 'Choose at least one data group to reset.', 'deleted' => []];
        }

        $tables = [];
        foreach ($requested as $group) {
            foreach ($groupTables[$group] as $table) {
                $tables[] = $table;
            }
        }
        $tables = array_values(array_unique($tables));

        $deleted = [];
        try {
            $this->db->beginTransaction();
            $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                if (!$this->hasColumn($table, 'tenant_id')) {
                    continue;
                }
                $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE tenant_id = ?");
                $stmt->execute([$tenantId]);
                $deleted[$table] = $stmt->rowCount();
            }
            $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->db->commit();

            return ['ok' => true, 'error' => null, 'deleted' => $deleted];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            try {
                $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable $ignored) {
            }
            error_log('TenantResetService::resetShopData failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not reset shop data. Try again or check the server log.', 'deleted' => []];
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = ?
                AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
