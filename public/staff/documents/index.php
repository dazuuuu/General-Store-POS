<?php
// public/staff/documents/index.php — document center for invoices, receipts,
// delivery notes, thank-you notes and remembrance notes.
require_once __DIR__ . '/../../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/order_invoice_email.php';
require_once ROOT_PATH . '/app/services/emails/order_delivery_note_email.php';
require_once ROOT_PATH . '/app/services/emails/order_thank_you_email.php';
require_once ROOT_PATH . '/app/services/emails/order_remembrance_email.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$O = new Models\OrderModel($pdo);
$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId()) ?: [];
$isStaffViewer = TenantContext::role() === 'staff';
$selfUrl = $isStaffViewer ? public_url('staff/documents/') : public_url('super/documents/');
$receiptBase = $isStaffViewer ? public_url('staff/orders/receipt.php') : public_url('super/orders/receipt.php');
$viewBase = $isStaffViewer ? public_url('staff/orders/view.php') : public_url('super/orders/view.php');

$shop = [
    'name' => $tenant['name'] ?? 'the shop',
    'phone' => $tenant['phone'] ?? '',
    'po_box' => $tenant['po_box'] ?? '',
    'email' => $tenant['business_email'] ?? '',
    'address' => $tenant['address'] ?? '',
    'kra_pin' => $tenant['kra_pin'] ?? '',
    'logo' => Branding::tenantLogo($tenant),
    'payment_credentials' => $tenant['payment_credentials'] ?? '',
];

$error = '';
function build_custom_note_email(array $order, array $shop, string $subject, string $body): array
{
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
    $shopName = $shop['name'] ?? 'the shop';
    $subject = trim($subject) !== '' ? trim($subject) : 'Note from ' . $shopName;
    $body = trim($body);
    $htmlBody = nl2br($h($body));
    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:32px 0">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="padding:24px 28px;border-bottom:1px solid #f1f5f9">
      <p style="margin:0;font-size:1.05rem;font-weight:700;color:#0f172a">{$h($shopName)}</p>
      <p style="margin:4px 0 0;font-size:.85rem;color:#64748b">{$h($order['receipt_number'] ?? '')}</p>
    </div>
    <div style="padding:28px;color:#475569;font-size:15px;line-height:1.55">
      <p style="margin:0 0 14px">Hi {$h($order['table_name'] ?? 'there')},</p>
      <div>{$htmlBody}</div>
    </div>
  </div>
</div>
HTML;
    $text = "Hi " . ($order['table_name'] ?? 'there') . ",\n\n" . $body . "\n\n" . $shopName;
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $order = $orderId > 0 ? $O->find($orderId) : null;
    if (!$order || ($order['status'] ?? '') === 'void') {
        $error = 'That order could not be found.';
    } elseif (empty($order['customer_email'])) {
        $error = 'Add a customer email before sending documents.';
    } else {
        $items = $O->items($orderId);
        $labels = [
            'send_invoice' => 'Invoice',
            'send_delivery_note' => 'Delivery note',
            'send_thank_you' => 'Thank-you note',
            'send_remembrance' => 'Remembrance note',
            'send_custom_note' => 'Custom note',
        ];
        if ($action === 'send_invoice') {
            $msg = build_order_invoice_email($order, $items, $shop);
        } elseif ($action === 'send_delivery_note') {
            $msg = build_order_delivery_note_email($order, $items, $shop);
        } elseif ($action === 'send_thank_you') {
            $msg = build_order_thank_you_email($order, $items, $shop);
        } elseif ($action === 'send_remembrance') {
            $msg = build_order_remembrance_email($order, $items, $shop);
        } elseif ($action === 'send_custom_note') {
            $customBody = trim((string) ($_POST['custom_message'] ?? ''));
            if ($customBody === '') {
                $msg = null;
                $error = 'Write the custom note before sending.';
            } else {
                $msg = build_custom_note_email($order, $shop, (string) ($_POST['custom_subject'] ?? ''), $customBody);
            }
        } else {
            $msg = null;
            $error = 'Choose a document to send.';
        }
        if ($msg) {
            if ((new MailService())->send($order['customer_email'], $msg['subject'], $msg['html'], $msg['text'])) {
                if ($action === 'send_invoice') { $O->markInvoiceSent($orderId); }
                elseif ($action === 'send_delivery_note') { $O->markDeliveryNoteSent($orderId); }
                elseif ($action === 'send_thank_you') { $O->markThankYouSent($orderId); }
                elseif ($action === 'send_remembrance') { $O->markRemembranceSent($orderId); }
                $_SESSION['flash']['success'] = ($labels[$action] ?? 'Document') . ' sent to ' . $order['customer_email'] . '.';
                header('Location: ' . $selfUrl);
                exit;
            }
            $error = 'Could not send the email: ' . (MailService::lastError() ?: 'unknown error');
        }
    }
}

$orders = $O->documentOrders(250);
$page_title = 'Documents';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h1 class="h5 fw-bold mb-1"><i class="fas fa-file-lines me-2 text-primary"></i>Documents</h1>
    <p class="text-muted small mb-0">Generate and send invoices, receipts, delivery notes, thank-you notes, and remembrance notes.</p>
  </div>
  <?php if ($isStaffViewer): ?>
    <a class="btn btn-sm btn-primary" href="<?php echo public_url('staff/bulk/'); ?>"><i class="fas fa-boxes-stacked me-1"></i>Bulk sale</a>
  <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr class="text-muted small text-uppercase">
          <th>Document</th>
          <th>Customer</th>
          <th>Email</th>
          <th>Status</th>
          <th class="text-end">Total</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$orders): ?>
          <tr><td colspan="6" class="text-center text-muted py-5">No invoices or receipts yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($orders as $o):
            $id = (int) $o['id'];
            $canEmail = !empty($o['customer_email']);
            $statusClass = $o['status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark';
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?php echo htmlspecialchars($o['receipt_number']); ?></div>
            <div class="text-muted small"><?php echo (int) $o['item_count']; ?> item<?php echo (int) $o['item_count'] === 1 ? '' : 's'; ?> · <?php echo date('j M, g:i a', strtotime($o['created_at'])); ?></div>
          </td>
          <td><?php echo htmlspecialchars($o['table_name']); ?></td>
          <td class="small"><?php echo $canEmail ? htmlspecialchars($o['customer_email']) : '<span class="text-muted">No email</span>'; ?></td>
          <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($o['status'])); ?></span></td>
          <td class="text-end fw-semibold">KES <?php echo number_format((float) $o['total'], 0); ?></td>
          <td class="text-end">
            <div class="d-flex flex-wrap justify-content-end gap-1">
              <a class="btn btn-sm btn-outline-secondary" href="<?php echo $viewBase . '?id=' . $id; ?>"><i class="fas fa-eye"></i></a>
              <a class="btn btn-sm btn-outline-primary" href="<?php echo $receiptBase . '?id=' . $id; ?>"><i class="fas fa-receipt"></i></a>
              <?php foreach ([
                  'send_invoice' => ['Invoice', 'fa-file-invoice'],
                  'send_delivery_note' => ['Delivery', 'fa-truck-ramp-box'],
                  'send_thank_you' => ['Thanks', 'fa-heart'],
                  'send_remembrance' => ['Reminder', 'fa-envelope-open-text'],
              ] as $act => $meta): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                  <button name="action" value="<?php echo $act; ?>" class="btn btn-sm btn-outline-secondary" <?php echo $canEmail ? '' : 'disabled'; ?> title="<?php echo htmlspecialchars($meta[0]); ?>">
                    <i class="fas <?php echo $meta[1]; ?>"></i>
                  </button>
                </form>
              <?php endforeach; ?>
            </div>
            <form method="post" class="mt-2">
              <input type="hidden" name="order_id" value="<?php echo $id; ?>">
              <input type="text" name="custom_subject" class="form-control form-control-sm mb-1" placeholder="Custom subject" <?php echo $canEmail ? '' : 'disabled'; ?>>
              <textarea name="custom_message" class="form-control form-control-sm mb-1" rows="2" placeholder="Custom note to customer" <?php echo $canEmail ? '' : 'disabled'; ?>></textarea>
              <button name="action" value="send_custom_note" class="btn btn-sm btn-outline-primary w-100" <?php echo $canEmail ? '' : 'disabled'; ?>>
                <i class="fas fa-paper-plane me-1"></i>Send custom note
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/' . ($isStaffViewer ? 'staff' : 'tenants') . '/layout.php';
