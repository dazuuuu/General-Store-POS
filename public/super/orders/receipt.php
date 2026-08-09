<?php
// public/super/orders/receipt.php?id=N — owner-side entry point for viewing
// a paid-tab receipt. Same page/logic as the staff till uses (receipt
// rendering doesn't depend on who's looking), but reached at a super/ URL so
// clicking "Receipt" from the owner's Sales/Dashboard never drops them onto
// a staff/ page. The page itself already adapts its "back" links by role.
require __DIR__ . '/../../staff/orders/receipt.php';
