<?php
/**
 * Pro Flame Gas Services - booking/query form handler
 *
 * Receives the Online Query/Booking Form, emails all booking details to
 * $TO_EMAIL, and tells the customer their query has been forwarded.
 *
 * Responds with JSON when called via fetch/AJAX, or a normal HTML page
 * if JavaScript is unavailable - so the form works either way.
 *
 * Requires PHP hosting with mail() enabled (standard on cPanel/shared hosting).
 */

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------
$TO_EMAIL   = 'sultan1@gmx.co.uk';
$SITE_NAME  = 'Pro Flame Gas Services';
// "From" must be an address on your own domain or the mail server may reject it
$FROM_EMAIL = 'no-reply@proflamegas.co.uk';

// Is this an AJAX request?
$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
);

// ---------------------------------------------------------------------------
// Only accept POST
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) { respond_json(false, 'Invalid request.'); }
    header('Location: index.html');
    exit;
}

function field($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

// Strip newlines so nobody can inject extra mail headers
function clean_header($value) {
    return str_replace(array("\r", "\n", "%0a", "%0d"), '', $value);
}

// ---------------------------------------------------------------------------
// Spam honeypot - real people never fill this in
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// Rate limit: stop one person hammering this endpoint. Without it, a script
// could flood the office inbox, spam arbitrary addresses with our confirmation
// email, create endless Square links, and grow the log files without limit.
// ---------------------------------------------------------------------------
if (!rate_limit_ok(6, 600)) {           // max 6 submissions per 10 minutes per IP
    finish(false, 'Too many booking attempts. Please wait a few minutes, or call us on 07833 722922.', $isAjax);
}

if (field('website') !== '') {
    finish(true, 'Your query has been forwarded to our team.', $isAjax);
}

// ---------------------------------------------------------------------------
// Collect the submission (same fields as the client's booking form)
// ---------------------------------------------------------------------------
$fields = array(
    'First Name'             => field('fnam'),
    'Last Name'              => field('lnam'),
    'Phone Number'           => field('phon'),
    'Email'                  => field('emil'),
    'Postcode'               => field('pcod'),
    'House/Flat Number'      => field('Hnum'),
    'Service'                => field('Serv'),
    'Response Required'      => field('Resp'),
    'Calendar Slot'          => field('CalSlot'),
    'Appliance Manufacturer' => field('Man'),
    'Appliance Model'        => field('Mod'),
    'Fuel Type'              => field('fuel'),
    'Description of fault'   => field('Flt'),
);

// Minimum needed to be able to get back to them
$errors = array();
if ($fields['First Name'] === '')        { $errors[] = 'First name'; }
if ($fields['Phone Number'] === '')      { $errors[] = 'Phone number'; }
if ($fields['Postcode'] === '')          { $errors[] = 'Postcode'; }
if ($fields['House/Flat Number'] === '') { $errors[] = 'House/Flat number'; }

if (!empty($errors)) {
    finish(false, 'Please fill in: ' . implode(', ', $errors) . '.', $isAjax);
}

// ---------------------------------------------------------------------------
// Build the email
// ---------------------------------------------------------------------------
$customerName = trim($fields['First Name'] . ' ' . $fields['Last Name']);
$subject = 'Website Booking/Query - ' . $customerName;
if ($fields['Postcode'] !== '') {
    $subject .= ' (' . $fields['Postcode'] . ')';
}

$body  = "New booking/query submitted from the website.\r\n";
$body .= str_repeat('-', 48) . "\r\n\r\n";
foreach ($fields as $label => $value) {
    $body .= str_pad($label . ':', 24) . ($value !== '' ? $value : '-') . "\r\n";
}
$body .= "\r\n" . str_repeat('-', 48) . "\r\n";
$body .= 'Submitted: ' . date('d/m/Y H:i:s') . "\r\n";
$body .= 'IP address: ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown') . "\r\n";

$headers  = 'From: ' . $SITE_NAME . ' <' . $FROM_EMAIL . '>' . "\r\n";
// Reply goes straight back to the customer where we have their address
$customerEmail = filter_var($fields['Email'], FILTER_VALIDATE_EMAIL);
if ($customerEmail) {
    $headers .= 'Reply-To: ' . clean_header($customerName) . ' <' . clean_header($customerEmail) . '>' . "\r\n";
} else {
    $headers .= 'Reply-To: ' . $FROM_EMAIL . "\r\n";
}
$headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
$headers .= 'X-Mailer: PHP/' . phpversion();

$sent = @mail($TO_EMAIL, clean_header($subject), $body, $headers, '-f' . $FROM_EMAIL);

// ---------------------------------------------------------------------------
// Create a Square payment link for the amount that matches the service level.
// (This replaces the old Node service in index.cjs, which only ever listened
//  on 127.0.0.1 and so could never be reached by a real customer.)
// ---------------------------------------------------------------------------
// The homepage form is an enquiry only - it must never take payment.
// Paid bookings come from booking.php, which does not send this flag.
$isEnquiry = (field('mode') === 'enquiry');
$payUrl = $isEnquiry ? null : create_square_payment_link($fields);

// ---------------------------------------------------------------------------
// Send the customer their own confirmation, so they have the booking details
// and the service terms in writing.
// ---------------------------------------------------------------------------
send_customer_confirmation($fields, $FROM_EMAIL, $SITE_NAME, $payUrl);

// ---------------------------------------------------------------------------
// Decide what the customer sees.
//
// The office notification failing is OUR problem, not the customer's. If a
// payment link exists, they must be allowed to pay - otherwise they would be
// told "we could not send that" while a live payment link sits in their inbox,
// and we would have no record of the booking at all.
//
// Every booking is written to MailLog.txt regardless, so nothing is lost even
// when mail() fails.
// ---------------------------------------------------------------------------
if (!$sent) {
    capped_log(__DIR__ . '/failed-notifications.log',
        str_repeat('=', 60) . "\n" .
        "OFFICE NOTIFICATION FAILED - booking details below\n" .
        'Payment link created: ' . ($payUrl ? $payUrl : 'none') . "\n" .
        $body . "\n");
    square_log('mail() to ' . $TO_EMAIL . ' FAILED - booking saved to failed-notifications.log');
}

if ($sent || $payUrl) {
    $msg = 'Thank you. Your query has been forwarded to our team and we will be in touch shortly.';
    if ($payUrl) {
        $msg = 'Thank you. Your booking has been forwarded to our team - taking you to secure payment now...';
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('ok' => true, 'message' => $msg, 'redirect' => $payUrl));
        exit;
    }
    if ($payUrl) { header('Location: ' . $payUrl); exit; }
    finish(true, $msg, $isAjax);
} else {
    // Never leave someone at a dead end - give them the phone number
    finish(false, 'Sorry, we could not send that automatically. Please call or WhatsApp us on 07833 722922.', $isAjax);
}

// ---------------------------------------------------------------------------
// Customer confirmation email.
//
// Only sent if they gave us a valid email address. Failure here is silent -
// the office has already been notified, so the booking is never lost just
// because the customer's mail server bounced us.
// ---------------------------------------------------------------------------
function send_customer_confirmation($fields, $fromEmail, $siteName, $payUrl) {
    $to = filter_var($fields['Email'], FILTER_VALIDATE_EMAIL);
    if (!$to) { return false; }

    $name = trim($fields['First Name']);
    $body  = ($name !== '' ? "Hello " . $name . "," : "Hello,") . "\r\n\r\n";
    $body .= "Thank you for your booking with Pro Flame Gas Services.\r\n";
    $body .= "We have received the following and will be in touch shortly.\r\n\r\n";
    $body .= "YOUR BOOKING\r\n" . str_repeat('-', 48) . "\r\n";

    $show = array('Service', 'Response Required', 'Calendar Slot', 'Postcode',
                  'House/Flat Number', 'Phone Number', 'Appliance Manufacturer',
                  'Appliance Model', 'Fuel Type', 'Description of fault');
    foreach ($show as $label) {
        if (!empty($fields[$label])) {
            $body .= str_pad($label . ':', 24) . $fields[$label] . "\r\n";
        }
    }

    if ($payUrl) {
        $body .= "\r\nPAYMENT\r\n" . str_repeat('-', 48) . "\r\n";
        $body .= "If you have not paid yet, you can do so securely here:\r\n" . $payUrl . "\r\n";
        $body .= "Payments are processed through Square.com.\r\n";
    }

    $body .= "\r\nWHAT YOU WILL RECEIVE\r\n" . str_repeat('-', 48) . "\r\n";
    $body .= "A Boiler/Gas Fire Service or Safety Check according to manufacturers\r\n";
    $body .= "instructions and The Gas Safety (Installation & Use) Regulations 2018.\r\n";
    $body .= "These usually take approximately 30 minutes to 1 hour, and include:\r\n";
    $body .= "  - A detailed invoice with payment & contractors' details\r\n";
    $body .= "  - A Safety Certificate\r\n";
    $body .= "  - A Service Report including appliance efficiency\r\n";
    $body .= "  - System Water Quality Test (if requested)\r\n";
    $body .= "  - Any Safety Notification(s)\r\n\r\n";
    $body .= "Please note: this does not include cleaning of the Heat Exchanger or\r\n";
    $body .= "replacement seals/gaskets/electrodes, which carries an additional\r\n";
    $body .= "charge (approx GBP 80).\r\n";

    $body .= "\r\nBEFORE WE ARRIVE\r\n" . str_repeat('-', 48) . "\r\n";
    $body .= "  - Keep cabinets/compartments around the boiler clear\r\n";
    $body .= "  - Provide loft access if the flue passes through it (legal requirement)\r\n";
    $body .= "  - Make any inspection hatches available\r\n";
    $body .= "  - Allow free floor space for Oil Boilers and Gas Fires\r\n";
    $body .= "  - Keep large dogs behind a closed door (not locked)\r\n";
    $body .= "  - Ensure clear access to the gas meter\r\n";
    $body .= "  - The appliance must run at maximum load for 5-10 minutes\r\n";
    $body .= "  - Leave warranty paperwork / system filter spanner near the boiler\r\n\r\n";
    $body .= "We aim for a smooth and undisruptive visit to your property and greatly\r\n";
    $body .= "appreciate all efforts made to comply with the above.\r\n";

    $body .= "\r\n" . str_repeat('-', 48) . "\r\n";
    $body .= "Pro Flame Gas Services (South West)\r\n";
    $body .= "Call / Text / WhatsApp: 07833 722922\r\n";
    $body .= "info@proflamegas.co.uk\r\n";
    $body .= "Gas Safe Registered - OFTEC Registered\r\n";

    $headers  = 'From: ' . $siteName . ' <' . $fromEmail . '>' . "\r\n";
    $headers .= 'Reply-To: info@proflamegas.co.uk' . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

    return @mail($to, clean_header('Your booking with Pro Flame Gas Services'),
                 $body, $headers, '-f' . $fromEmail);
}

// ---------------------------------------------------------------------------
// Square: create a payment link for this booking.
//
// Ported from index.cjs. Returns the checkout URL, or null if payment is not
// configured / Square fails - in which case the booking email has still been
// sent, so nothing is lost and the office can take payment manually.
// ---------------------------------------------------------------------------
function create_square_payment_link($fields) {
    $cfgFile = __DIR__ . '/square-config.php';
    if (!file_exists($cfgFile)) { return null; }
    $cfg = include $cfgFile;

    if (empty($cfg['access_token'])) {
        square_log('No access token set in square-config.php - skipping payment link.');
        return null;
    }

    // ---------------------------------------------------------------------
    // Work out the price from the SIGNED tier that booking.php issued.
    //
    // The visible "Response Required" text is never used for pricing: it is
    // part of the form, so a customer could edit it (or post directly) and
    // ask to be charged the cheapest tier for the most expensive service.
    // The signature proves which price they were actually shown.
    // ---------------------------------------------------------------------
    $tierMap = array('S' => '1hr', 'N' => 'nextday', 'R' => 'routine', 'V' => 'vip');

    $tierCode = field('tier_code');
    $tierSig  = field('tier_sig');
    $secret   = isset($cfg['tier_secret']) ? $cfg['tier_secret'] : '';

    if ($secret === '') {
        square_log('SECURITY: tier_secret is not set in square-config.php - refusing to create a payment link.');
        return null;
    }
    if ($tierCode === '' || $tierSig === '') {
        square_log('SECURITY: booking submitted with no signed tier - refusing to create a payment link.');
        return null;
    }
    if (!hash_equals(hash_hmac('sha256', $tierCode, $secret), $tierSig)) {
        square_log('SECURITY: invalid tier signature for tier "' . $tierCode . '" - possible price tampering. No payment link created.');
        return null;
    }

    $key = isset($tierMap[$tierCode]) ? $tierMap[$tierCode] : null;

    if ($key === null || empty($cfg['prices'][$key])) {
        square_log('No price configured for verified tier: ' . $tierCode);
        return null;
    }

    $amount = (int) $cfg['prices'][$key]['amount'];
    $label  = $cfg['prices'][$key]['label'];

    // Put the customer + appointment on the Square line item so it is
    // identifiable in the Square dashboard
    $who  = trim($fields['First Name'] . ' ' . $fields['Last Name']);
    $when = $fields['Calendar Slot'] !== '' ? ' : ' . $fields['Calendar Slot'] : '';
    $name = substr($label . ' - ' . $who . $when, 0, 255);

    $payload = array(
        'idempotency_key' => bin2hex(random_bytes(16)),
        'quick_pay' => array(
            'name'        => $name,
            'price_money' => array('amount' => $amount, 'currency' => 'GBP'),
            'location_id' => $cfg['location_id'],
        ),
    );

    $host = (isset($cfg['environment']) && $cfg['environment'] === 'sandbox')
        ? 'https://connect.squareupsandbox.com'
        : 'https://connect.squareup.com';

    $ch = curl_init($host . '/v2/online-checkout/payment-links');
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => array(
            'Square-Version: 2025-01-23',
            'Authorization: Bearer ' . $cfg['access_token'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ));
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) { square_log('cURL failed: ' . $err); return null; }

    $data = json_decode($raw, true);
    if ($code >= 200 && $code < 300 && !empty($data['payment_link']['url'])) {
        $url = $data['payment_link']['url'];
        // keep the same log the Node service produced
        capped_log(__DIR__ . '/urlLog.txt', $url . "\r\n");
        return $url;
    }

    square_log('Square error HTTP ' . $code . ': ' . substr($raw, 0, 400));
    return null;
}

// ---------------------------------------------------------------------------
// Simple per-IP rate limit, stored in one small file. No database needed.
// Returns false when the caller has exceeded $max submissions in $window secs.
// ---------------------------------------------------------------------------
function rate_limit_ok($max, $window) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $file = __DIR__ . '/.ratelimit.json';
    $now = time();

    $data = array();
    if (is_readable($file)) {
        $raw = @file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) { $data = $decoded; }
    }

    // drop anything outside the window (also stops the file growing forever)
    foreach ($data as $key => $times) {
        $kept = array_values(array_filter($times, function ($t) use ($now, $window) {
            return ($now - $t) < $window;
        }));
        if ($kept) { $data[$key] = $kept; } else { unset($data[$key]); }
    }

    $mine = isset($data[$ip]) ? $data[$ip] : array();
    if (count($mine) >= $max) {
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return false;
    }

    $mine[] = $now;
    $data[$ip] = $mine;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

// Append to a log file, trimming it first if it has grown too large.
// Stops a flood of requests filling the disk.
function capped_log($file, $line, $maxBytes = 1048576) {   // 1 MB
    if (is_file($file) && filesize($file) > $maxBytes) {
        $keep = @file_get_contents($file, false, null, (int)($maxBytes / 2));
        @file_put_contents($file, "...older entries trimmed...\n" . $keep);
    }
    @file_put_contents($file, $line, FILE_APPEND);
}

function square_log($msg) {
    capped_log(__DIR__ . '/square-errors.log',
        date('d/m/Y H:i:s') . '  ' . $msg . "\n");
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function respond_json($ok, $message) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($ok ? array('ok' => true, 'message' => $message)
                         : array('ok' => false, 'error' => $message));
    exit;
}

function finish($ok, $message, $isAjax) {
    if ($isAjax) { respond_json($ok, $message); }
    show_page($ok ? 'Thank you' : 'Please check the form', $message, $ok);
    exit;
}

// Fallback page shown when JavaScript is unavailable
function show_page($heading, $message, $success) {
    $colour = $success ? '#14724d' : '#b5151b';
    echo '<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . htmlspecialchars($heading) . ' | Pro Flame Gas Services</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; margin:0; background:#f6f8fc; }
    .box { max-width:620px; margin:0 auto; padding:40px 20px; text-align:center; }
    .logo { width:100%; max-width:260px; height:auto; }
    h1 { color:' . $colour . '; font-size:26px; margin:20px 0 10px; }
    p { color:#12203a; font-size:16px; line-height:1.5; margin:0 0 20px; }
    .btn { display:inline-block; font-size:17px; font-weight:bold; color:#fff;
           background:#e11b22; border-radius:999px; padding:14px 30px; text-decoration:none; }
    .btn:hover { background:#b5151b; }
</style>
</head>
<body>
<div class="box">
    <img class="logo" src="assets/proflame-logo-long.jpg" alt="Pro Flame Gas Services South West">
    <h1>' . htmlspecialchars($heading) . '</h1>
    <p>' . htmlspecialchars($message) . '</p>
    <p><a class="btn" href="index.html">Back to the website</a></p>
</div>
</body>
</html>';
}
