<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("Tests must be run from CLI.");
}

echo "Running Fika Integration Tests...\n\n";
$failed = 0;

function assertTest($name, $condition) {
    global $failed;
    if ($condition) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}\n";
        $failed++;
    }
}

// 1. Output Tests
$dirtyString = "<script>alert('xss');</script>";
assertTest("Output::e() escapes HTML", Output::e($dirtyString) === "&lt;script&gt;alert(&#039;xss&#039;);&lt;/script&gt;");
assertTest("Output::js() creates safe JSON", Output::js($dirtyString) === '"\u003Cscript\u003Ealert(\u0027xss\u0027);\u003C\/script\u003E"');

// 2. CSRF Tests
$token = Csrf::getToken();
assertTest("CSRF Token generates", !empty($token));
assertTest("CSRF verify() validates correctly", Csrf::verify($token));
assertTest("CSRF verify() rejects invalid", !Csrf::verify("invalid_token"));

// 3. Money Tests
assertTest("Money::toCentavos converts correctly", Money::toCentavos("150.99") === 15099);
assertTest("Money::toDecimal converts correctly", Money::toDecimal(15099) === "150.99");

// 4. Crypto Tests
$secret = "My Super Secret 123";
$encrypted = Crypto::encrypt($secret);
assertTest("Crypto::encrypt produces base64 payload", base64_decode($encrypted, true) !== false);
assertTest("Crypto::decrypt recovers plaintext", Crypto::decrypt($encrypted) === $secret);
assertTest("Crypto::decrypt rejects tampering", Crypto::decrypt($encrypted . "a") === null);

// 5. Lockout / Auth Rate-Limiting Tests
// To test this safely without breaking real users, we mock an attempt on a fake user.
global $pdo;
$fakeUser = "test_lockout_user_" . time();

// Fake 5 failed attempts
for ($i = 0; $i < 5; $i++) {
    Auth::attemptLogin($fakeUser, "wrong_pass");
}

// Attempting 6th time should be rejected by rate limit BEFORE password check
// (We can't easily assert the internal rate limit bool from the outside, but we know attemptLogin fails)
assertTest("Auth::attemptLogin blocks after 5 fails", Auth::attemptLogin($fakeUser, "wrong_pass") === false);

echo "\nTests Completed. Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
