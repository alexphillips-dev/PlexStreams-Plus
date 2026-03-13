<?php
declare(strict_types=1);

$_SERVER['REMOTE_USER'] = 'viewer';

require_once __DIR__ . '/../../src/plexstreamsplus/usr/local/emhttp/plugins/plexstreamsplus/includes/common.php';

function assertTrueCondition($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        exit(1);
    }
}

function assertSameValue($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            "Assertion failed: " . $message . " (expected: " . var_export($expected, true) . ", actual: " . var_export($actual, true) . ")" . PHP_EOL
        );
        exit(1);
    }
}

$cfgNonAdmin = [
    'MASK_USERNAMES' => '1',
    'MASK_LOCATIONS' => '1',
    'PRIVACY_ROLE' => 'non_admin',
    'ALLOW_TERMINATE' => '1'
];

$streams = [[
    'user' => 'alice',
    'userAvatar' => 'https://gravatar.example/avatar.png',
    'location' => 'WAN',
    'locationDisplay' => 'WAN (10.0.0.10 - Toronto, CA)',
    'address' => '10.0.0.10'
]];

$masked = applyPrivacyRules($streams, $cfgNonAdmin);
assertSameValue('User 1', $masked[0]['user'], 'non-admin should mask usernames');
assertSameValue('WAN (hidden)', $masked[0]['locationDisplay'], 'non-admin should mask location display');
assertSameValue('hidden', $masked[0]['address'], 'non-admin should mask address');
assertTrueCondition(
    safeTextOrEmpty($masked[0]['userOriginal'] ?? '') !== '',
    'original username should be preserved in metadata'
);
assertSameValue(false, canViewerTerminateSessions($cfgNonAdmin), 'non-admin should not be allowed to terminate');

$_SERVER['REMOTE_USER'] = 'root';
$adminUnmasked = applyPrivacyRules($streams, $cfgNonAdmin);
assertSameValue('alice', $adminUnmasked[0]['user'], 'admin should not be masked with non_admin privacy scope');
assertSameValue(true, canViewerTerminateSessions($cfgNonAdmin), 'admin should be allowed to terminate when enabled');

$cfgAll = $cfgNonAdmin;
$cfgAll['PRIVACY_ROLE'] = 'all';
$adminMasked = applyPrivacyRules($streams, $cfgAll);
assertSameValue('User 1', $adminMasked[0]['user'], 'admin should be masked when scope is all');
assertSameValue('WAN (hidden)', $adminMasked[0]['locationDisplay'], 'admin location should be masked when scope is all');

assertSameValue('https://10.0.0.5:32400', normalizeHostUrl('https://10.0.0.5:32400'), 'normalizeHostUrl should keep valid https host');
assertSameValue('http://plex.local:32400', normalizeHostUrl('plex.local:32400'), 'normalizeHostUrl should add http scheme');
assertSameValue(null, normalizeHostUrl('http://'), 'normalizeHostUrl should reject invalid hosts');

echo "PHP tests passed: common_privacy_helpers.test.php" . PHP_EOL;

function safeTextOrEmpty($value) {
    return trim((string)$value);
}
