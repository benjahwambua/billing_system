<?php
require_once __DIR__ . '/../includes/sms_gateway.php';

$cases = [
    ['name' => 'public IPv4 HTTPS endpoint is allowed', 'url' => 'https://8.8.8.8/api/send', 'expected' => true],
    ['name' => 'public IPv6 HTTPS endpoint is allowed', 'url' => 'https://[2606:4700:4700::1111]/api/send', 'expected' => true],
    ['name' => 'plain HTTP is rejected', 'url' => 'http://8.8.8.8/api/send', 'expected' => false],
    ['name' => 'loopback IPv4 is rejected', 'url' => 'https://127.0.0.1/api/send', 'expected' => false],
    ['name' => 'private IPv4 is rejected', 'url' => 'https://10.0.0.5/api/send', 'expected' => false],
    ['name' => 'link-local metadata IP is rejected', 'url' => 'https://169.254.169.254/latest/meta-data/', 'expected' => false],
    ['name' => 'reserved documentation IP is rejected', 'url' => 'https://192.0.2.10/api/send', 'expected' => false],
    ['name' => 'localhost is rejected', 'url' => 'https://localhost/api/send', 'expected' => false],
    ['name' => 'local network hostname is rejected', 'url' => 'https://gateway.local/api/send', 'expected' => false],
    ['name' => 'embedded URL credentials are rejected', 'url' => 'https://user:secret@8.8.8.8/api/send', 'expected' => false],
    ['name' => 'nonstandard port is rejected', 'url' => 'https://8.8.8.8:8443/api/send', 'expected' => false],
    ['name' => 'URL fragment is rejected', 'url' => 'https://8.8.8.8/api/send#private', 'expected' => false],
];

$failed = 0;
foreach ($cases as $case) {
    $actual = flexihubSmsEndpointPolicy($case['url'])['valid'];
    if ($actual !== $case['expected']) {
        fwrite(STDERR, "FAIL: {$case['name']}\n");
        $failed++;
    } else {
        echo "PASS: {$case['name']}\n";
    }
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} SMS endpoint policy test(s) failed.\n");
    exit(1);
}

echo count($cases) . " SMS endpoint policy tests passed.\n";
