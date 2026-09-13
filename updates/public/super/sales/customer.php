<?php
// public/super/sales/customer.php?name=... — owner-side entry point for a
// customer's full history. Same page/logic the staff Sales page uses, just
// reached at a super/ URL so clicking a customer from the owner's
// Sales/Dashboard never drops them onto a staff/ page.
require __DIR__ . '/../../staff/sales/customer.php';
