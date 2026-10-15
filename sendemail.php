<?php
// Handles the Book a Service and Share Your Experience forms.
// Needs PHP hosting with mail() enabled (GoDaddy cPanel works).
header('Content-Type: application/json');
$TO   = 'info@wamts.me';
$FROM = 'no-reply@wamts.me';      // must be an address on your own domain for reliable delivery

function out($ok, $msg){ echo json_encode(['ok'=>$ok,'message'=>$msg]); exit; }
function clean($v, $max = 1000){
  $v = trim(strip_tags((string)$v));
  $v = str_replace(["\r","\n"], ' ', $v);     // blocks email header injection
  return mb_substr($v, 0, $max);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Invalid request.');
if (!empty($_POST['website'])) out(true, 'Thank you.');   // honeypot: bots fill this hidden field

$type  = ($_POST['type'] ?? '') === 'review' ? 'review' : 'booking';
$name  = clean($_POST['name'] ?? '', 80);
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
if ($name === '') out(false, 'Please enter your name.');

if ($type === 'booking') {
  $phone = clean($_POST['phone'] ?? '', 30);
  if ($phone === '') out(false, 'Please enter your phone number.');
  $svc  = clean($_POST['service'] ?? '', 120);
  $area = clean($_POST['area'] ?? '', 120);
  $msg  = clean($_POST['msg'] ?? '', 1500);
  $subject = "New service request: $svc";
  $body = "Name: $name\nPhone: $phone\nEmail: $email\nService: $svc\nArea: $area\n\nDetails:\n$msg\n";
} else {
  $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
  $msg = clean($_POST['msg'] ?? '', 800);
  if ($msg === '') out(false, 'Please write your feedback.');
  $entry = ['name'=>$name, 'rating'=>$rating, 'text'=>$msg, 'date'=>date('Y-m-d')];
  // Saved as PENDING. To publish: copy the entry into reviews.json.
  $file = __DIR__ . '/reviews-pending.json';
  $list = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
  $list[] = $entry;
  file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
  $subject = "New customer review ($rating/5) awaiting approval";
  $body = "Name: $name\nRating: $rating/5\n\n$msg\n\nTo publish, add this to reviews.json:\n"
        . json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
}

$headers = "From: Wadi Al Mishmish Website <$FROM>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
if ($email) $headers .= "Reply-To: $email\r\n";
$sent = mail($TO, $subject, $body, $headers);
if (!$sent) out(false, 'Could not send right now. Please call or WhatsApp us.');
out(true, $type === 'review' ? 'Thank you! Your review will appear once approved.' : 'Thank you! We will contact you shortly.');
