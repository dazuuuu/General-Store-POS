# Modern Shop POS

General shop point-of-sale: counter sales, barcode checkout, inventory (single + bulk), credit sales / B2B invoicing, loyalty, tiered pricing, VAT, receipts, and unified reports.

## Receipt footer (default)

```
GOODS ONCE SOLD ARE NOT REACCEPTED
!THANK YOU FOR VISITING OUR BUSINESS!
!!WELCOME!!
```

Invoices are printed on receipts and can be emailed with delivery notes, thank-you notes, and remembrance notes.

## Migration

Apply `databases/migrations/044_general_shop_pos.sql` (or rely on runtime `ensureSchema` helpers on Product/Tenant/Customer models).
