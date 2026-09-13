<?php

class PaymentOptions
{
    /** Settle methods shown on payments desk (admin can enable/reorder via settings). */
    public static function settleMethods(): array
    {
        return [
            'cash' => 'Cash',
            'mpesa' => 'M-Pesa',
            'paybill' => 'Paybill / Till',
            'card' => 'Card',
            'bank' => 'Bank',
            'sacco' => 'SACCO',
            'split' => 'Split (Cash + M-Pesa)',
        ];
    }

    /** Default enabled order — admin settings override this list. */
    public static function defaultEnabledMethods(): array
    {
        return ['cash', 'mpesa', 'paybill', 'card', 'bank', 'sacco', 'split'];
    }

    /**
     * Enabled settle methods for the current tenant, in admin priority order.
     * Stored as JSON on tenants.payment_methods_json.
     */
    public static function enabledSettleMethods(?array $tenant = null): array
    {
        $all = self::settleMethods();
        $raw = is_array($tenant) ? ($tenant['payment_methods_json'] ?? null) : null;
        $ids = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            } else {
                $ids = array_map('trim', explode(',', $raw));
            }
        }
        if (!$ids) {
            $ids = self::defaultEnabledMethods();
        }
        $out = [];
        foreach ($ids as $id) {
            $id = strtolower(trim((string) $id));
            if (isset($all[$id])) {
                $out[$id] = $all[$id];
            }
        }
        return $out ?: $all;
    }

    public static function depositMethods(?array $tenant = null): array
    {
        $enabled = self::enabledSettleMethods($tenant);
        unset($enabled['split']);
        return $enabled ?: [
            'cash' => 'Cash',
            'mpesa' => 'M-Pesa',
            'paybill' => 'Paybill / Till',
            'card' => 'Card',
            'bank' => 'Bank',
            'sacco' => 'SACCO',
        ];
    }

    public static function cardTypes(): array
    {
        return [
            'M-Pesa Global',
            'Visa',
            'Mastercard',
            'American Express',
            'Discover',
            'Diners Club',
            'JCB',
            'UnionPay',
            'Maestro',
            'PayPal',
            'Verve',
            'RuPay',
        ];
    }

    public static function kenyaBanks(): array
    {
        return [
            'ABSA Bank Kenya',
            'Access Bank Kenya',
            'African Banking Corporation',
            'Bank of Africa Kenya',
            'Bank of Baroda Kenya',
            'Bank of India',
            'Citibank N.A. Kenya',
            'Consolidated Bank of Kenya',
            'Co-operative Bank of Kenya',
            'Credit Bank',
            'Development Bank of Kenya',
            'Diamond Trust Bank Kenya',
            'DIB Bank Kenya',
            'Ecobank Kenya',
            'Equity Bank Kenya',
            'Family Bank',
            'First Community Bank',
            'Guaranty Trust Bank Kenya',
            'Guardian Bank',
            'Gulf African Bank',
            'Habib Bank A.G. Zurich',
            'HFC',
            'I&M Bank',
            'KCB Bank Kenya',
            'Kingdom Bank',
            'Mayfair-CIB Bank',
            'Middle East Bank Kenya',
            'M-Oriental Bank',
            'National Bank of Kenya',
            'NCBA Bank Kenya',
            'Paramount Bank',
            'Prime Bank',
            'SBM Bank Kenya',
            'Sidian Bank',
            'Stanbic Bank Kenya',
            'Standard Chartered Bank Kenya',
            'UBA Kenya Bank',
            'Victoria Commercial Bank',
        ];
    }

    public static function kenyaSaccos(): array
    {
        return [
            '2NK Sacco',
            'Acumen DT Sacco',
            'Afya Sacco',
            'Agrochem Sacco',
            'Ainabkoi Sacco',
            'Airports Sacco',
            'Amica Sacco',
            'Ammar Sacco',
            'Apstar DT Sacco',
            'Ardhi Sacco',
            'Asili Sacco',
            'Azima Sacco',
            'Bandari Sacco',
            'Baraka Sacco',
            'Baringo Teachers Sacco',
            'Boresha Sacco',
            'Boresha DT Sacco',
            'Chai Sacco',
            'Chuna Sacco',
            'Chuka University Sacco',
            'Comoco Sacco',
            'Cosmopolitan Sacco',
            'Daima Sacco',
            'Dimkes Sacco',
            'Dhabiti Sacco',
            'Egerton University Sacco',
            'Elimu Sacco',
            'Faridi Sacco',
            'Fortune Sacco',
            'Gusii Mwalimu Sacco',
            'Harambee Sacco',
            'Hazina Sacco',
            'Imarika Sacco',
            'Jamii Sacco',
            'Kenpipe Sacco',
            'Kenversity Sacco',
            'Kenya Bankers Sacco',
            'Kenya National Police DT Sacco',
            'Kenya USA Diaspora Sacco',
            'Kimisitu Sacco',
            'Kimisitu DT Sacco',
            'K-Unity Sacco',
            'Lamu Teachers Sacco',
            'Magereza Sacco',
            'Maisha Bora Sacco',
            'Mentor Sacco',
            'Metropolitan National Sacco',
            'Mhasibu Sacco',
            'Mombasa Port Sacco',
            'Mwalimu National Sacco',
            'Nacico Sacco',
            'Nawiri Sacco',
            'Nyati Sacco',
            'Safaricom Sacco',
            'Sheria Sacco',
            'Stima Sacco',
            'Tai Sacco',
            'Tower Sacco',
            'Ukulima Sacco',
            'Unaitas Sacco',
            'United Nations DT Sacco',
            'Universal Traders Sacco',
            'Ushuru Sacco',
            'Waumini Sacco',
            'Winas Sacco',
        ];
    }

    public static function label(array $row): string
    {
        $method = $row['payment_method'] ?? $row['method'] ?? 'cash';
        $provider = trim((string) ($row['payment_provider'] ?? $row['provider'] ?? ''));
        $account = trim((string) ($row['payment_account_name'] ?? $row['account_name'] ?? ''));
        $base = [
            'cash' => 'Cash',
            'mpesa' => 'M-Pesa',
            'paybill' => 'Paybill / Till',
            'split' => 'Split',
            'card' => 'Card',
            'bank' => 'Bank',
            'sacco' => 'SACCO',
            'credit' => 'Credit',
        ][$method] ?? ucfirst((string) $method);

        $detail = $provider !== '' ? $provider : $account;
        return $detail !== '' ? $base . ' - ' . $detail : $base;
    }
}
