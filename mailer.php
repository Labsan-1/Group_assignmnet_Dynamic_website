<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer manually (no Composer)
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';

// ================== CONFIG ==================
const APP_FROM_EMAIL = 'labsanghimire12@gmail.com';  // keep SAME as SMTP_USER for Gmail
const APP_FROM_NAME  = 'Web';

const SMTP_USER = 'labsanghimire12@gmail.com';       // your Gmail address
const SMTP_PASS = 'ponyhaorljwmqtqn';                // 16-char Gmail App Password (no spaces)

// Change to your app’s base URL (no trailing slash)
const BASE_URL  = 'http://localhost/Simple';
// ============================================

/** Create and configure PHPMailer with Gmail SMTP. */
function makeMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    // $mail->SMTPDebug  = 2;            // uncomment while debugging
    // $mail->Debugoutput = 'error_log';

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // XAMPP/Windows helper: relax TLS verification during local dev.
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ];

    $mail->setFrom(APP_FROM_EMAIL, APP_FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

/** Send account verification email. 
 * @return true|string  true on success, or error text on failure
 */
function sendVerificationEmail(string $toEmail, string $toName, string $token) {
    $verifyLink = BASE_URL . '/verify.php?token=' . urlencode($token);
    $mail = makeMailer();
    try {
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'Verify your account';

        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($verifyLink, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <p>Hi <strong>{$safeName}</strong>,</p>
            <p>Please click the link below to verify your account:</p>
            <p><a href='{$safeLink}'>Verify Account</a></p>
            <p>If that doesn’t work, copy this URL into your browser:<br>{$safeLink}</p>
        ";
        $mail->AltBody = "Hi {$toName},\n\nVerify your account here: {$verifyLink}\n";

        return $mail->send() ? true : ($mail->ErrorInfo ?: 'Send failed');
    } catch (Exception $e) {
        return $mail->ErrorInfo ?: $e->getMessage();
    }
}

/** Send "Order Confirmed" receipt email. 
 * $order: ['ids'=>[101,102], 'total'=>999.00, 'currency'=>'NPR', 'payment_method'=>'Cash on Delivery', 'shipping'=>'...']
 * $items: [['name'=>'Product', 'qty'=>2, 'price'=>499.50], ...]   // price is line total
 * @return true|string
 */
function sendOrderConfirmedEmail(string $toEmail, string $toName, array $order, array $items) {
    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $idsList  = implode(', ', array_map('intval', $order['ids'] ?? []));
    $total    = number_format((float)($order['total'] ?? 0), 2);
    $currency = htmlspecialchars($order['currency'] ?? 'NPR', ENT_QUOTES, 'UTF-8');
    $method   = htmlspecialchars($order['payment_method'] ?? '—', ENT_QUOTES, 'UTF-8');
    $shipping = nl2br(htmlspecialchars($order['shipping'] ?? '', ENT_QUOTES, 'UTF-8'));

    $rows = '';
    foreach ($items as $it) {
        $n = htmlspecialchars($it['name'] ?? 'Item', ENT_QUOTES, 'UTF-8');
        $q = (int)($it['qty'] ?? 1);
        $p = number_format((float)($it['price'] ?? 0), 2); // line total
        $rows .= "<tr>
          <td style='padding:6px 8px;border:1px solid #eee'>{$n}</td>
          <td style='padding:6px 8px;border:1px solid #eee;text-align:center'>{$q}</td>
          <td style='padding:6px 8px;border:1px solid #eee;text-align:right'>{$currency} {$p}</td>
        </tr>";
    }

    $html = "
      <div style='font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif'>
        <h2 style='margin:0 0 10px'>Thanks, {$safeName}!</h2>
        <p>Your order <strong>#{$idsList}</strong> has been <strong>confirmed</strong>.</p>
        <table cellpadding='0' cellspacing='0' style='border-collapse:collapse;margin:12px 0;width:100%;max-width:560px'>
          <thead>
            <tr>
              <th style='text-align:left;padding:8px;border:1px solid #eee;background:#f8f8f8'>Item</th>
              <th style='text-align:center;padding:8px;border:1px solid #eee;background:#f8f8f8'>Qty</th>
              <th style='text-align:right;padding:8px;border:1px solid #eee;background:#f8f8f8'>Line Total</th>
            </tr>
          </thead>
          <tbody>{$rows}</tbody>
          <tfoot>
            <tr>
              <td colspan='2' style='padding:8px;border:1px solid #eee;text-align:right'><strong>Total</strong></td>
              <td style='padding:8px;border:1px solid #eee;text-align:right'><strong>{$currency} {$total}</strong></td>
            </tr>
          </tfoot>
        </table>
        <p><strong>Payment method:</strong> {$method}</p>"
        . ($shipping ? "<p><strong>Shipping to:</strong><br>{$shipping}</p>" : "") .
      "</div>
    ";

    $alt = "Hi {$toName},\n\nYour order #{$idsList} is confirmed.\nTotal: {$currency} {$total}\nPayment method: {$method}\n";

    $mail = makeMailer();
    try {
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = "Order confirmed (#{$idsList})";
        $mail->Body    = $html;
        $mail->AltBody = $alt;

        return $mail->send() ? true : ($mail->ErrorInfo ?: 'Send failed');
    } catch (Exception $e) {
        return $mail->ErrorInfo ?: $e->getMessage();
    }
}
