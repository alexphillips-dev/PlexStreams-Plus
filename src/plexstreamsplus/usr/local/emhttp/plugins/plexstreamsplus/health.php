<?php
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/common.php');

    header('Content-type: application/json');

    $token = trim((string)($cfg['TOKEN'] ?? ''));
    $configuredHosts = normalizeHostCollection(getConfiguredHosts($cfg));
    $tokenProbe = probePlexToken($token);
    $selectedHostHealth = [];
    if ($tokenProbe['configured']) {
        $selectedHostHealth = collectHostHealth($configuredHosts, $token, 10);
    }

    $reachableSelectedCount = 0;
    foreach ($selectedHostHealth as $healthEntry) {
        if (!empty($healthEntry['reachable'])) {
            $reachableSelectedCount += 1;
        }
    }

    $autoFailoverEnabled = cfgEnabled($cfg, 'AUTO_FAILOVER', '1');
    $fallbackCandidates = [];
    $fallbackHealth = [];
    $recommendedFallback = '';
    if ($autoFailoverEnabled && !empty($tokenProbe['valid']) && $reachableSelectedCount === 0) {
        $selectedSet = [];
        foreach ($configuredHosts as $host) {
            $selectedSet[$host] = true;
        }

        foreach (getDiscoveredConnections($cfg) as $connection) {
            $uri = (string)($connection['uri'] ?? '');
            if ($uri === '' || isset($selectedSet[$uri])) {
                continue;
            }
            $fallbackCandidates[] = [
                'host' => $uri,
                'name' => (string)($connection['name'] ?? ''),
                'local' => ((string)($connection['local'] ?? '0') === '1')
            ];
            if (count($fallbackCandidates) >= 5) {
                break;
            }
        }

        if (count($fallbackCandidates) > 0) {
            $fallbackHosts = array_map(function ($candidate) {
                return (string)$candidate['host'];
            }, $fallbackCandidates);
            $fallbackHealth = collectHostHealth($fallbackHosts, $token, 10);
            foreach ($fallbackHealth as $candidateHealth) {
                if (!empty($candidateHealth['reachable'])) {
                    $recommendedFallback = (string)($candidateHealth['host'] ?? '');
                    break;
                }
            }
        }
    }

    $message = 'Health check complete.';
    if (!$tokenProbe['configured']) {
        $message = 'No Plex token configured.';
    } else if (!$tokenProbe['valid']) {
        $message = 'Token appears invalid or expired.';
    } else if (count($configuredHosts) === 0) {
        $message = 'No configured Plex hosts selected.';
    } else if ($reachableSelectedCount === 0) {
        $message = 'No selected hosts are reachable.';
    } else {
        $message = $reachableSelectedCount . ' selected host(s) reachable.';
    }

    echo(json_encode([
        'viewerRole' => getViewerRole(),
        'timestamp' => time(),
        'token' => $tokenProbe,
        'autoFailover' => [
            'enabled' => $autoFailoverEnabled,
            'recommendedHost' => $recommendedFallback
        ],
        'selectedHosts' => $selectedHostHealth,
        'fallbackCandidates' => $fallbackCandidates,
        'fallbackHealth' => $fallbackHealth,
        'message' => $message
    ]));

