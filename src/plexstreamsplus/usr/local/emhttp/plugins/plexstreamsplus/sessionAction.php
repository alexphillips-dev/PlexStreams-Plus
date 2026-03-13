<?php
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/common.php');

    header('Content-type: application/json');

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        echo(json_encode([
            'ok' => false,
            'message' => 'Method not allowed.'
        ]));
        exit;
    }

    $action = strtolower(trim((string)($_POST['action'] ?? 'terminate')));
    if ($action !== 'terminate') {
        http_response_code(400);
        echo(json_encode([
            'ok' => false,
            'message' => 'Unsupported action.'
        ]));
        exit;
    }

    if (!canViewerTerminateSessions($cfg)) {
        http_response_code(403);
        echo(json_encode([
            'ok' => false,
            'message' => 'Terminate action is disabled or not permitted.'
        ]));
        exit;
    }

    $host = normalizeHostUrl($_POST['host'] ?? '');
    $sessionId = trim((string)($_POST['sessionId'] ?? ($_POST['streamId'] ?? '')));
    $reason = trim((string)($_POST['reason'] ?? 'Terminated by PlexStreams Plus'));
    if ($host === null || $sessionId === '') {
        http_response_code(400);
        echo(json_encode([
            'ok' => false,
            'message' => 'Missing host or session id.'
        ]));
        exit;
    }

    $allowedHosts = getAllowedHosts($cfg);
    if (!in_array($host, $allowedHosts, true)) {
        http_response_code(403);
        echo(json_encode([
            'ok' => false,
            'message' => 'Host is not in the allowed Plex host list.'
        ]));
        exit;
    }

    $token = trim((string)($cfg['TOKEN'] ?? ''));
    if ($token === '') {
        http_response_code(500);
        echo(json_encode([
            'ok' => false,
            'message' => 'No Plex token configured.'
        ]));
        exit;
    }

    $result = terminatePlexSession($host, $token, $sessionId, $reason);
    if (!empty($result['ok'])) {
        echo(json_encode([
            'ok' => true,
            'message' => 'Terminate command sent.',
            'status' => $result['status'] ?? 'ok'
        ]));
        exit;
    }

    $httpCode = (int)($result['httpCode'] ?? 500);
    if ($httpCode >= 400) {
        http_response_code($httpCode);
    } else {
        http_response_code(502);
    }
    echo(json_encode([
        'ok' => false,
        'message' => (string)($result['message'] ?? 'Terminate action failed.'),
        'status' => (string)($result['status'] ?? 'error')
    ]));

