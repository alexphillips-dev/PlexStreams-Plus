<?php
    if (isset($GLOBALS['unRaidSettings'])) {
        define('OS_VERSION', 'Unraid ' . $GLOBALS['unRaidSettings']['version']);
    }
    define('PLUGIN_VERSION', '2026.03.13.4');

    if (!function_exists('getInstalledPluginVersion')) {
        function getInstalledPluginVersion($fallbackVersion) {
            $bestVersion = trim((string)$fallbackVersion);
            $candidateManifests = [
                '/boot/config/plugins/plexstreamsplus/plexstreamsplus.plg',
                '/boot/config/plugins/plexstreams/plexstreams.plg'
            ];

            foreach ($candidateManifests as $manifestPath) {
                if (!is_readable($manifestPath)) {
                    continue;
                }

                $manifestContents = @file_get_contents($manifestPath);
                if ($manifestContents === false) {
                    continue;
                }

                if (preg_match('/<!ENTITY\s+version\s+"([^"]+)"/', $manifestContents, $matches) === 1) {
                    $resolvedVersion = trim((string)($matches[1] ?? ''));
                    if ($resolvedVersion === '') {
                        continue;
                    }

                    if ($bestVersion === '') {
                        $bestVersion = $resolvedVersion;
                        continue;
                    }

                    // Prefer the highest version so stale local manifests cannot
                    // make the settings badge display an older release.
                    if (version_compare($resolvedVersion, $bestVersion, '>')) {
                        $bestVersion = $resolvedVersion;
                    }
                }
            }

            return $bestVersion;
        }
    }

    if (!defined('PLUGIN_DISPLAY_VERSION')) {
        define('PLUGIN_DISPLAY_VERSION', getInstalledPluginVersion(PLUGIN_VERSION));
    }

    if (!function_exists('cfgEnabled')) {
        function cfgEnabled($cfg, $key, $default = '0') {
            $raw = isset($cfg[$key]) ? (string)$cfg[$key] : (string)$default;
            return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
        }
    }

    if (!function_exists('getCurrentUiUser')) {
        function getCurrentUiUser() {
            $candidates = [
                $_SERVER['REMOTE_USER'] ?? '',
                $_SERVER['PHP_AUTH_USER'] ?? '',
                $_SERVER['AUTH_USER'] ?? '',
                $_SERVER['HTTP_X_AUTH_NAME'] ?? ''
            ];
            foreach ($candidates as $candidate) {
                $value = trim((string)$candidate);
                if ($value !== '') {
                    return $value;
                }
            }
            return '';
        }
    }

    if (!function_exists('getViewerRole')) {
        function getViewerRole() {
            $user = strtolower(getCurrentUiUser());
            if ($user === '' || $user === 'root' || $user === 'admin') {
                return 'admin';
            }
            return 'user';
        }
    }

    if (!function_exists('privacyRoleMode')) {
        function privacyRoleMode($cfg) {
            $mode = strtolower(trim((string)($cfg['PRIVACY_ROLE'] ?? 'non_admin')));
            if (!in_array($mode, ['non_admin', 'all'], true)) {
                return 'non_admin';
            }
            return $mode;
        }
    }

    if (!function_exists('privacyRuleApplies')) {
        function privacyRuleApplies($cfg) {
            $mode = privacyRoleMode($cfg);
            if ($mode === 'all') {
                return true;
            }
            return getViewerRole() !== 'admin';
        }
    }

    if (!function_exists('shouldMaskUsernames')) {
        function shouldMaskUsernames($cfg) {
            return cfgEnabled($cfg, 'MASK_USERNAMES', '0') && privacyRuleApplies($cfg);
        }
    }

    if (!function_exists('shouldMaskLocations')) {
        function shouldMaskLocations($cfg) {
            return cfgEnabled($cfg, 'MASK_LOCATIONS', '0') && privacyRuleApplies($cfg);
        }
    }

    if (!function_exists('canViewerTerminateSessions')) {
        function canViewerTerminateSessions($cfg) {
            return cfgEnabled($cfg, 'ALLOW_TERMINATE', '0') && getViewerRole() === 'admin';
        }
    }

    if (!function_exists('maskDisplayName')) {
        function maskDisplayName($rawName, &$knownUsers, &$counter) {
            $name = trim((string)$rawName);
            if ($name === '') {
                return 'User Hidden';
            }
            if (!isset($knownUsers[$name])) {
                $counter += 1;
                $knownUsers[$name] = 'User ' . $counter;
            }
            return $knownUsers[$name];
        }
    }

    if (!function_exists('maskedLocationLabel')) {
        function maskedLocationLabel($location) {
            $scope = strtoupper(trim((string)$location));
            if ($scope === '' || $scope === 'UNKNOWN') {
                return 'UNKNOWN (hidden)';
            }
            if (strpos($scope, 'LAN') !== false || strpos($scope, 'LOCAL') !== false) {
                return 'LAN (hidden)';
            }
            return 'WAN (hidden)';
        }
    }

    if (!function_exists('applyPrivacyRules')) {
        function applyPrivacyRules($streams, $cfg) {
            if (!is_array($streams) || count($streams) === 0) {
                return $streams;
            }

            $maskUsers = shouldMaskUsernames($cfg);
            $maskLocations = shouldMaskLocations($cfg);
            if (!$maskUsers && !$maskLocations) {
                return $streams;
            }

            $knownUsers = [];
            $counter = 0;
            foreach ($streams as &$stream) {
                if ($maskUsers) {
                    $stream['userOriginal'] = $stream['user'] ?? '';
                    $stream['user'] = maskDisplayName($stream['user'] ?? '', $knownUsers, $counter);
                    $stream['userAvatar'] = '/plugins/plexstreamsplus/PlexStreams-icon.png';
                }

                if ($maskLocations) {
                    $stream['locationDisplayOriginal'] = $stream['locationDisplay'] ?? '';
                    $stream['addressOriginal'] = $stream['address'] ?? '';
                    $stream['locationDisplay'] = maskedLocationLabel($stream['location'] ?? '');
                    $stream['address'] = 'hidden';
                }
            }
            unset($stream);

            return $streams;
        }
    }

    function normalizeHostUrl($host) {
        $host = trim((string)$host);
        if ($host === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $host)) {
            $host = 'http://' . $host;
        }

        $parts = parse_url($host);
        if ($parts === false || !isset($parts['scheme']) || !isset($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        return $scheme . '://' . $parts['host'] . ':' . $port;
    }

    function buildAliasKey($host) {
        return 'ALIAS-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$host);
    }

    function splitHostList($rawHosts) {
        $hosts = [];
        foreach (explode(',', (string)$rawHosts) as $host) {
            $normalized = normalizeHostUrl($host);
            if ($normalized !== null) {
                $hosts[$normalized] = true;
            }
        }

        return array_keys($hosts);
    }

    function getConfiguredHosts($cfg) {
        $hosts = [];
        foreach (splitHostList($cfg['HOST'] ?? '') as $host) {
            $hosts[$host] = true;
        }
        foreach (splitHostList($cfg['CUSTOM_SERVERS'] ?? '') as $host) {
            $hosts[$host] = true;
        }

        return array_keys($hosts);
    }

    function isConfiguredHost($host, $cfg) {
        $normalized = normalizeHostUrl($host);
        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, getConfiguredHosts($cfg), true);
    }

    function isPlexDomain($host) {
        $host = strtolower((string)$host);
        return $host === 'plex.tv' ||
            preg_match('/(?:^|\.)plex\.tv$/', $host) === 1 ||
            preg_match('/(?:^|\.)gravatar\.com$/', $host) === 1;
    }

    function shouldSkipSslVerification($url) {
        $parts = parse_url((string)$url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === 'localhost' || substr($host, -6) === '.local') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            // Allow self-signed certs for local/private network hosts only.
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        }

        return false;
    }

    function safeLoadXml($xmlString) {
        if (!is_string($xmlString) || trim($xmlString) === '') {
            return false;
        }

        $previousState = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);
        if ($xml === false) {
            return false;
        }

        return json_decode(json_encode($xml), true);
    }

    function buildCurlHandle($url, $timeout = 30, $headers = []) {
        $ch = curl_init();
        $skipSslVerification = shouldSkipSslVerification($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $skipSslVerification ? 0 : 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $skipSslVerification ? 0 : 1);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        if (is_array($headers) && count($headers) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        return $ch;
    }

    function appendTokenToUrl($url, $token) {
        if ((string)$token === '') {
            return $url;
        }

        return $url . (strpos($url, '?') !== false ? '&' : '?') . 'X-Plex-Token=' . urlencode($token);
    }

    function executeCurlRequest($url, $timeout = 15, $headers = [], $method = 'GET', $body = null) {
        $requestMethod = strtoupper(trim((string)$method));
        if ($requestMethod === '') {
            $requestMethod = 'GET';
        }

        $start = microtime(true);
        $ch = buildCurlHandle($url, $timeout, $headers);
        if ($requestMethod !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestMethod);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        $elapsedMs = (int)round((microtime(true) - $start) * 1000);

        return [
            'url' => $url,
            'body' => ($responseBody !== false) ? $responseBody : '',
            'httpCode' => $httpCode,
            'errno' => $errno,
            'error' => $error,
            'durationMs' => $elapsedMs,
            'ok' => ($errno === 0 && $httpCode >= 200 && $httpCode < 300)
        ];
    }

    function normalizeHostCollection($hosts) {
        $normalized = [];
        foreach ((array)$hosts as $host) {
            $uri = normalizeHostUrl($host);
            if ($uri !== null) {
                $normalized[$uri] = true;
            }
        }
        return array_keys($normalized);
    }

    function getDiscoveredConnections($cfg) {
        $servers = getServers($cfg);
        if (!is_array($servers)) {
            return [];
        }

        $connections = [];
        foreach ($servers as $server) {
            $serverName = (string)($server['Name'] ?? '');
            foreach ((array)($server['Connections'] ?? []) as $connection) {
                $uri = normalizeHostUrl($connection['uri'] ?? '');
                if ($uri === null) {
                    continue;
                }
                $connections[$uri] = [
                    'uri' => $uri,
                    'name' => $serverName,
                    'address' => (string)($connection['address'] ?? ''),
                    'local' => (string)($connection['local'] ?? '0')
                ];
            }
        }

        $ordered = array_values($connections);
        usort($ordered, function ($a, $b) {
            $aLocal = (($a['local'] ?? '0') === '1') ? 1 : 0;
            $bLocal = (($b['local'] ?? '0') === '1') ? 1 : 0;
            if ($aLocal !== $bLocal) {
                return $bLocal <=> $aLocal;
            }
            return strcmp((string)($a['uri'] ?? ''), (string)($b['uri'] ?? ''));
        });

        return $ordered;
    }

    function getAllowedHosts($cfg) {
        $allowed = [];
        foreach (normalizeHostCollection(getConfiguredHosts($cfg)) as $uri) {
            $allowed[$uri] = true;
        }
        foreach (getDiscoveredConnections($cfg) as $connection) {
            $uri = (string)($connection['uri'] ?? '');
            if ($uri !== '') {
                $allowed[$uri] = true;
            }
        }
        return array_keys($allowed);
    }

    function probePlexToken($token) {
        $trimmed = trim((string)$token);
        if ($trimmed === '') {
            return [
                'configured' => false,
                'valid' => false,
                'status' => 'missing',
                'httpCode' => 0,
                'durationMs' => 0,
                'message' => 'No Plex token configured.'
            ];
        }

        $url = appendTokenToUrl('https://plex.tv/api/v2/user', $trimmed);
        $probe = executeCurlRequest($url, 15, ['Accept: application/json']);
        $isUnauthorized = in_array((int)$probe['httpCode'], [401, 403], true);
        $isValid = ($probe['ok'] && !$isUnauthorized);
        $status = $isValid ? 'ok' : ($isUnauthorized ? 'expired' : 'error');
        $message = $isValid ? 'Token is valid.' : ($isUnauthorized ? 'Token is unauthorized or expired.' : 'Unable to verify token.');

        return [
            'configured' => true,
            'valid' => $isValid,
            'status' => $status,
            'httpCode' => (int)$probe['httpCode'],
            'durationMs' => (int)$probe['durationMs'],
            'message' => $message,
            'error' => (string)($probe['error'] ?? '')
        ];
    }

    function probePlexHostSessionEndpoint($host, $token, $timeout = 12) {
        $normalizedHost = normalizeHostUrl($host);
        if ($normalizedHost === null) {
            return [
                'host' => (string)$host,
                'reachable' => false,
                'activeStreams' => 0,
                'httpCode' => 0,
                'durationMs' => 0,
                'status' => 'invalid_host',
                'message' => 'Invalid host URI.'
            ];
        }

        $url = appendTokenToUrl($normalizedHost . '/status/sessions?_m=' . time(), $token);
        $probe = executeCurlRequest($url, $timeout, ['Accept: application/xml']);
        $content = safeLoadXml($probe['body']);
        $video = (isset($content['Video']) ? $content['Video'] : []);
        $track = (isset($content['Track']) ? $content['Track'] : []);
        $videoCount = isset($video['@attributes']) ? 1 : count((array)$video);
        $trackCount = isset($track['@attributes']) ? 1 : count((array)$track);
        $activeStreams = max(0, $videoCount + $trackCount);

        $httpCode = (int)$probe['httpCode'];
        $reachable = ((int)$probe['errno'] === 0 && $httpCode >= 200 && $httpCode < 400);
        $status = $reachable ? 'ok' : (($httpCode === 401 || $httpCode === 403) ? 'unauthorized' : 'unreachable');
        $message = $reachable ? 'Reachable' : (((int)$probe['errno'] !== 0) ? (string)$probe['error'] : ('HTTP ' . $httpCode));

        return [
            'host' => $normalizedHost,
            'reachable' => $reachable,
            'activeStreams' => $activeStreams,
            'httpCode' => $httpCode,
            'durationMs' => (int)$probe['durationMs'],
            'status' => $status,
            'message' => $message
        ];
    }

    function collectHostHealth($hosts, $token, $timeout = 12) {
        $results = [];
        foreach (normalizeHostCollection($hosts) as $host) {
            $results[] = probePlexHostSessionEndpoint($host, $token, $timeout);
        }
        return $results;
    }

    function hasReachableSessionResponse($responses) {
        if (!is_array($responses)) {
            return false;
        }
        foreach ($responses as $idx => $details) {
            if (strpos((string)$idx, 'streams-') !== 0) {
                continue;
            }
            $httpCode = (int)($details['httpCode'] ?? 0);
            $errno = (int)($details['curlErrno'] ?? 0);
            if ($errno === 0 && $httpCode >= 200 && $httpCode < 400) {
                return true;
            }
        }
        return false;
    }

    function terminatePlexSession($host, $token, $sessionId, $reason = '') {
        $normalizedHost = normalizeHostUrl($host);
        $trimmedToken = trim((string)$token);
        $trimmedSessionId = trim((string)$sessionId);
        if ($normalizedHost === null || $trimmedToken === '' || $trimmedSessionId === '') {
            return [
                'ok' => false,
                'status' => 'invalid_request',
                'message' => 'Missing required termination details.',
                'httpCode' => 0
            ];
        }

        $query = 'sessionId=' . rawurlencode($trimmedSessionId);
        $trimmedReason = trim((string)$reason);
        if ($trimmedReason !== '') {
            $query .= '&reason=' . rawurlencode($trimmedReason);
        }
        $url = appendTokenToUrl($normalizedHost . '/status/sessions/terminate?' . $query, $trimmedToken);
        $probe = executeCurlRequest($url, 15, ['Accept: application/xml']);
        $httpCode = (int)$probe['httpCode'];
        $ok = ((int)$probe['errno'] === 0 && $httpCode >= 200 && $httpCode < 300);

        return [
            'ok' => $ok,
            'status' => $ok ? 'ok' : (($httpCode === 401 || $httpCode === 403) ? 'unauthorized' : 'error'),
            'message' => $ok ? 'Session terminate command accepted.' : (((int)$probe['errno'] !== 0) ? (string)$probe['error'] : ('HTTP ' . $httpCode)),
            'httpCode' => $httpCode
        ];
    }

    function getGeo($ip) {
        $url = 'https://plex.tv/api/v2/geoip?ip_address=' . $ip;
        $resp = getUrl($url);
        if (isset($resp['@attributes'])) {
            return $resp['@attributes']['city'] . ', ' . (isset($resp['@attributes']['subdivision']) ? $resp['@attributes']['subdivision'] . ' ' : '' ) . $resp['@attributes']['code'];
        }
    }

    function getServers($cfg) {
        $token = trim((string)($cfg['TOKEN'] ?? ''));
        if ($token === '') {
            return [];
        }

        $url = appendTokenToUrl('https://plex.tv/devices.xml', $token);
        $url2 = appendTokenToUrl(
            'https://plex.tv/api/resources' . ((($cfg['FORCE_PLEX_HTTPS'] ?? '0') === '1') ? '?includeHttps=1' : ''),
            $token
        );
        if (isset($_REQUEST['dbg'])) {
            v_d($url);
        }
        $servers = getUrl($url);
        if ($servers === false) {
            return false;
        }

        $serverList = [];
        if (isset($servers['@attributes'])) {
            $servers = [$servers];
        }
        foreach($servers as $server) {
            if (!isset($server['Device'])) {
                continue;
            }
            if (isset($server['Device']['@attributes'])) {
                $server['Device'] = [$server['Device']];
            }
            foreach($server['Device'] as $device) {
                if (isset($device['@attributes']['provides'])) {
                    $providers = explode(',', $device['@attributes']['provides']);
                    if (in_array('server', $providers, true)) {
                        $serverList[$device['@attributes']['clientIdentifier']] = [
                            'Name' => $device['@attributes']['name'],
                            'Identifier' => $device['@attributes']['clientIdentifier'],
                            'Connections' => []
                        ];
                    }
                }
            }
        }

        if (count($serverList) > 0) {
            $connections = getUrl($url2);
            if ($connections !== false && isset($connections['Device'])) {
                $devices = $connections['Device'];
                if (isset($devices['@attributes'])) {
                    $devices = [$devices];
                }
                foreach($devices as $device) {
                    $identifier = $device['@attributes']['clientIdentifier'] ?? null;
                    if ($identifier === null || !isset($serverList[$identifier])) {
                        continue;
                    }
                    if (!isset($device['Connection'])) {
                        continue;
                    }
                    $deviceConnections = $device['Connection'];
                    if (isset($deviceConnections['@attributes'])) {
                        $deviceConnections = [$deviceConnections];
                    }
                    foreach($deviceConnections as $connection) {
                        if (isset($connection['@attributes'])) {
                            $serverList[$identifier]['Connections'][] = $connection['@attributes'];
                        }
                    }
                }
            }
        }

        return $serverList;
    }

    function getServerCheckboxes($cfg) {
        $servers = getServers($cfg);
        $retVal = '<div id="HOST">';
        $selected = explode(',', (string)($cfg['HOST'] ?? ''));
        foreach($servers as $server) {
            foreach($server['Connections'] as $connection) {
                $url = $connection['uri'];
                $retVal .= '<input onchange="updateServerList(\'HOST\')" name="hostbox" data-id="' . $server['Identifier'] . '" id="' .$url .'" type="checkbox" value="'  .$url .'"' .(in_array($url, $selected) ? ' checked="checked"' : '') . '> <label for="' . $url . '"/>' .$server['Name'] .' (' . $connection['address'] . ':' .$connection['port'] .')' . ($connection['local'] === '0' ? ' - Remote' : '') . '</label></br>';
            }
        }

        $retVal .= '</div>';
        

        return $retVal;
    }

    function generateServerList($cfg, $name, $id, $selected) {
        $servers = getServers($cfg);
        $retVal = '
                <select name="' .$name . '" id="' .$id .'">
        ';
        foreach($servers as $server) {
            foreach($server['Connections'] as $connection) {
                $url = $connection['uri'];
                $retVal .= '<option value="'  .$url .'"' .($selected === $url ? ' selected="selected"' : '') . '>' .$server['Name'] .' (' . $connection['address'] . ':' .$connection['port'] .')' . ($connection['local'] === '0' ? ' - Remote' : '') . '</option>';
            }
        }
        $retVal .= '</select>';

        return $retVal;
    }

    function getStreams($cfg) {
        $token = trim((string)($cfg['TOKEN'] ?? ''));
        if ($token === '') {
            return [];
        }

        $hosts = getConfiguredHosts($cfg);
        if (count($hosts) === 0) {
            return [];
        }

        $streams = [];
        $schedules = [];
        foreach($hosts as $host) {
            $streams[] = appendTokenToUrl($host . '/status/sessions?_m=' . time(), $token);
            $schedules[] = appendTokenToUrl($host . '/media/subscriptions/scheduled', $token);
            if (isset($_REQUEST['dbg'])) {
                v_d($streams);
                v_d($schedules);
            }
        }
        $combined = $streams;
        array_push($combined, ...$schedules);
        $primaryResults = getUrl($combined);
        if (!cfgEnabled($cfg, 'AUTO_FAILOVER', '1')) {
            return $primaryResults;
        }
        if (hasReachableSessionResponse($primaryResults)) {
            return $primaryResults;
        }

        $configuredSet = [];
        foreach ($hosts as $configuredHost) {
            $configuredSet[(string)$configuredHost] = true;
        }

        $fallbackHosts = [];
        foreach (getDiscoveredConnections($cfg) as $connection) {
            $uri = (string)($connection['uri'] ?? '');
            if ($uri === '' || isset($configuredSet[$uri])) {
                continue;
            }
            $fallbackHosts[] = $uri;
            if (count($fallbackHosts) >= 3) {
                break;
            }
        }

        if (count($fallbackHosts) === 0) {
            return $primaryResults;
        }

        $fallbackStreams = [];
        $fallbackSchedules = [];
        foreach ($fallbackHosts as $fallbackHost) {
            $fallbackStreams[] = appendTokenToUrl($fallbackHost . '/status/sessions?_m=' . time(), $token);
            $fallbackSchedules[] = appendTokenToUrl($fallbackHost . '/media/subscriptions/scheduled', $token);
        }
        $fallbackCombined = $fallbackStreams;
        array_push($fallbackCombined, ...$fallbackSchedules);
        $fallbackResults = getUrl($fallbackCombined);
        if (hasReachableSessionResponse($fallbackResults)) {
            foreach ($fallbackResults as &$fallbackResult) {
                if (is_array($fallbackResult)) {
                    $fallbackResult['usedFailover'] = true;
                }
            }
            unset($fallbackResult);
            return $fallbackResults;
        }

        return $primaryResults;
    }

    function v_d($obj) {
        echo('<pre>');
        var_dump($obj);
        echo('</pre>');
    }

    function getUrl($urls) {
        if (is_array($urls)) {
            $rets = [];
            $multi = [];
            $requestUrls = [];
            $mh = curl_multi_init();
            foreach($urls as $idx=>$url) {
                $prefix = '';
                if (stripos($url, 'sessions') !== false) {
                    $prefix = 'streams-';
                } else if (stripos($url, 'schedule') !== false) {
                    $prefix = 'schedules-';
                }

                $id = $prefix . $idx;
                $requestUrls[$id] = $url;
                $multi[$id] = buildCurlHandle($url, 30);
                curl_multi_add_handle($mh, $multi[$id]);
            }
            // execute the handles
            do {
                $mrc = curl_multi_exec($mh, $active);
            }
            while ($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($active && $mrc == CURLM_OK) {
                if (curl_multi_select($mh) != -1) {
                    do {
                        $mrc = curl_multi_exec($mh, $active);
                    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
                }
            }

            foreach($multi as $idx=>$m) {
                $contentString = curl_multi_getcontent($m);
                if (isset($_REQUEST['dbg'])) {
                    v_d($contentString);
                }

                $effectiveUrl = curl_getinfo($m, CURLINFO_EFFECTIVE_URL);
                if (empty($effectiveUrl) && isset($requestUrls[$idx])) {
                    $effectiveUrl = $requestUrls[$idx];
                }
                $rets[$idx]['url'] = $effectiveUrl;
                $rets[$idx]['httpCode'] = (int)curl_getinfo($m, CURLINFO_RESPONSE_CODE);
                $rets[$idx]['curlErrno'] = curl_errno($m);
                $rets[$idx]['curlError'] = curl_error($m);
                $rets[$idx]['durationMs'] = (int)round(((float)curl_getinfo($m, CURLINFO_TOTAL_TIME)) * 1000);
                $content = safeLoadXml($contentString);
                $rets[$idx]['content'] = ($content !== false) ? $content : [];

                curl_multi_remove_handle($mh, $m);
                curl_close($m);
            }

            curl_multi_close($mh);
            return $rets;
        }

        $ch = buildCurlHandle($urls, 30);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || ($httpCode > 0 && $httpCode >= 400)) {
            return false;
        }

        $content = safeLoadXml($body);
        return ($content !== false) ? $content : false;
    }

    function mergeStreams($allStreams, $cfg) {
        global $display;

        $mergedStreams = [];
        $videoStreams = [];
        $schedules = [];
        foreach($allStreams as $idx=>$details) {
            $urlParts = parse_url($details['url']);
            if ($urlParts !== false && isset($urlParts['scheme']) && isset($urlParts['host'])) {
                $source = (is_array($details['content'])) ? $details['content'] : [];
                $port = $urlParts['port'] ?? ($urlParts['scheme'] === 'https' ? 443 : 80);
                $source['@host'] = $urlParts['scheme'] . '://' . $urlParts['host'] . ':' . $port;
                $source['shortHost'] = $urlParts['host'];
                if (stripos($idx, 'streams-') !== false) {
                    $videoStreams[] = $source;
                } else if (stripos($idx, 'schedules-') !== false) {
                    $schedules[] = $source;
                }
            }
        }

        foreach($videoStreams as $streams) {
            if (isset($streams['Video'])) {
                if (isset($streams['Video']) && isset($streams['Video']['@attributes'])) {
                    $streams['Video'] = [$streams['Video']];
                }
                foreach($streams['Video'] as $idx=>$video) {
                    
                    if (isset($video['Media']['@attributes'])) {
                        $video['Media'] = [$video['Media']];
                    }
                    foreach($video['Media'] as $media) {
                        if (isset($media['@attributes']['selected']) && $media['@attributes']['selected'] === '1') {
                            if (!isset($media['@attributes']['origin'])) {
                                $title = $video['@attributes']['title'] . (isset($video['@attributes']['year']) ? ' (' . $video['@attributes']['year'] . ')' : '' );
                                if (isset($video['@attributes']['parentTitle'])) {
                                    $title = $video['@attributes']['parentTitle'] . ' - ' . $title;
                                }
                                if (isset($video['@attributes']['grandparentTitle']) && $video['@attributes']['grandparentTitle'] !== $title) {
                                    $title = $video['@attributes']['grandparentTitle'] . ' - ' . $title;
                                }
                            } else  {
                                $title = $video['@attributes']['title'];
                            }
                            if (isset($media['Part']['@attributes']['duration'])) {
                                $duration = $media['Part']['@attributes']['duration'];
                                $lengthInSeconds = $duration / 1000;
                                $lengthInMinutes = ceil($lengthInSeconds / 60 );
                                $lengthSeconds = floor(intval($lengthInSeconds)%60);
                                $lengthMinutes = floor((intval($lengthInSeconds)%3600)/60);
                                $lengthHours = floor((intval($lengthInSeconds)%86400)/3600);
                                
                                $currentPosition = floatval((int)$video['@attributes']['viewOffset']);
                                $currentPositionInSeconds = $video['@attributes']['viewOffset'] / 1000;
                                $currentPositionInMinutes = ceil($currentPositionInSeconds / 60);
                                $currentPositionSeconds = floor((int)$currentPositionInSeconds%60);
                                $currentPositionMinutes = floor(((int)$currentPositionInSeconds%3600)/60);
                                $currentPositionHours = floor(((int)$currentPositionInSeconds%86400)/3600);
                                $endSecondsFromNow = ceil($lengthInSeconds - $currentPositionInSeconds);
                                
                                $endTime = date('h:i A', strtotime('+ ' . $endSecondsFromNow . ' seconds'));
                                if ($display['time'] == '%R' && $display['date'] != '%c') {
                                    $endTime = date('H:i', strtotime('+ ' . $endSecondsFromNow . ' seconds'));
                                }
                            } else {
                                $duration = null;
                            }
                            if (isset($video['@attributes']['art'])) {
                                $artThumb = $video['@attributes']['art'];
                            } else {
                                if (isset($media['@attributes']['channelThumb'])) {
                                    $artThumb = $media['@attributes']['channelThumb'];
                                } else {
                                    $artThumb = '';
                                }
                            }

                            $addr = (string)$streams['shortHost'];
                            $alias = '';
                            $aliasKey = buildAliasKey($addr);
                            
                            if (isset($cfg[$aliasKey])) {
                                $alias = $cfg[$aliasKey];
                            }

                            $mergedStream = [
                                '@host' => $streams['@host'],
                                'alias' => $alias,
                                'id' => $media['@attributes']['id'],
                                'sessionId' => $video['@attributes']['sessionKey'] ?? ($video['Session']['@attributes']['id'] ?? null),
                                'machineIdentifier' => $video['@attributes']['machineIdentifier'] ?? '',
                                'type' => 'video',
                                'product' => $video['Player']['@attributes']['product'],
                                'player' => $video['Player']['@attributes']['title'] ?? $video['Player']['@attributes']['product'],
                                'title' => $title,
                                'titleString' => $title,
                                'key' => $video['@attributes']['key'],
                                'duration' => $duration,
                                'artUrl' => '/plugins/plexstreamsplus/getImage.php?img=' . urlencode($artThumb) . '&host=' . urlencode($streams['@host']),
                                'thumbUrl' => '/plugins/plexstreamsplus/getImage.php?img=' .  urlencode($video['@attributes']['grandparentThumb'] ?? $video['@attributes']['thumb']) . '&host=' . urlencode($streams['@host']),
                                'user' => $video['User']['@attributes']['title'],
                                'userAvatar' => $video['User']['@attributes']['thumb'],
                                'state' => $video['Player']['@attributes']['state'],
                                'stateIcon' => 'play',
                                'length' => $duration ?? null,
                                'lengthInSeconds' => $lengthInSeconds ?? null,
                                'lengthInMinutes' => $lengthInMinutes ?? null,
                                'lengthSeconds' => $lengthInSeconds ?? null,
                                'lengthMinutes' => $lengthMinutes ?? null,
                                'lengthHours' => $lengthHours ?? null,
                                'currentPosition' => $currentPosition ?? null,
                                'currentPositionInSeconds' =>  $currentPositionInSeconds ?? null,
                                'currentPositionInMinutes' =>  $currentPositionInMinutes ?? null,
                                'currentPositionHours' => $currentPositionHours ?? null,
                                'currentPositionMinutes' => $currentPositionMinutes ?? null,
                                'currentPositionSeconds' => $currentPositionSeconds ?? null,
                                'location' => $video['Session']['@attributes']['location'],
                                'address' => $video['Player']['@attributes']['address'],
                                'bandwidth' => round((int)$video['Session']['@attributes']['bandwidth'] / 1000, 1),
                                'endSecondsFromNow' => (isset($endSecondsFromNow) ? ceil($endSecondsFromNow) : null),
                                'endTime' => (isset($endTime) ? $endTime : null),
                                'streamInfo' => []
                            ];

                            if (isset($alias)) {
                                $mergedStream['alias'] = $alias;
                            }
                            if (($mergedStream['location'] === null || $mergedStream['location'] === '') && (($video['Player']['@attributes']['local'] ?? '0') === '1')) {
                                $mergedStream['location'] = 'LAN';
                            }
                            $loc = strtoupper((string)($mergedStream['location'] ?? 'UNKNOWN'));
                            $mergedStream['locationDisplay'] = $loc . ' (' . $mergedStream['address'] . ($loc !== 'LAN' ? ' - ' .getGeo($mergedStream['address']) : '' ) . ')';
                            
                            if ($mergedStream['duration'] !== null) {
                                $mergedStream['percentPlayed'] = round(($currentPositionInMinutes/ $lengthInMinutes) * 100, 0);
                                $mergedStream['currentPositionDisplay'] = str_pad($currentPositionHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionSeconds, 2, '0', STR_PAD_LEFT);
                                $mergedStream['lengthDisplay'] = str_pad($lengthHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthSeconds, 2, '0', STR_PAD_LEFT);
                            } else {
                                $mergedStream['percentPlayed'] = 0;
                            }

                            if ($mergedStream['state'] === 'paused') {
                                $mergedStream['stateIcon'] = 'pause';
                            } else if ($mergedStream['state'] !== 'playing') {
                                $mergedStream['stateIcon'] = 'buffer';
                            }

                            if (isset($media['Part']['Stream'])) {
                                foreach ($media['Part']['Stream'] as $stream) {
                                    if ($stream['@attributes']['streamType'] === '2') {
                                        $mergedStream['streamInfo']['audio'] = $stream;
                                        $mergedStream['streamInfo']['audio']['@attributes']['decision'] = $mergedStream['streamInfo']['audio']['@attributes']['decision'] ?? 'direct play';
                                    } else if ($stream['@attributes']['streamType'] === '1') {
                                        $mergedStream['streamInfo']['video'] = $stream;
                                        $mergedStream['streamInfo']['video']['@attributes']['decision'] = $mergedStream['streamInfo']['video']['@attributes']['decision'] ?? 'direct play';
                                    } else if ($stream['@attributes']['streamType'] === '3') {
                                        $mergedStream['streamInfo']['subtitle'] = $stream;
                                        $mergedStream['streamInfo']['subtitle']['@attributes']['decision'] =
                                            $mergedStream['streamInfo']['subtitle']['@attributes']['decision'] ??
                                            ($mergedStream['streamInfo']['subtitle']['@attributes']['displayTitle'] ?? 'none');
                                    }
                                }
                            }
                            
                            $mergedStream['streamDecision'] = $media['Part']['@attributes']['decision'];
                            if ($mergedStream['streamDecision'] === 'directplay') {
                                $mergedStream['streamDecision'] = 'Direct Play';
                            }

                            if ($mergedStream['streamDecision'] === 'transcode') {
                                if (($mergedStream['streamInfo']['video']['@attributes']['decision'] ?? '') === 'transcode') {
                                    $videoDecision = $mergedStream['streamInfo']['video']['@attributes']['decision'];
                                    $hwTag = (($video['TranscodeSession']['@attributes']['transcodeHwRequested'] ?? '0') === '1') ? ' (HW)' : '';
                                    $displayTitle = $mergedStream['streamInfo']['video']['@attributes']['displayTitle'] ?? '';
                                    $resolution = $media['@attributes']['videoResolution'] ?? '';
                                    $mergedStream['streamInfo']['video']['@attributes']['decision'] = trim(
                                        $videoDecision . $hwTag . ($displayTitle !== '' || $resolution !== '' ? ' ' . $displayTitle . ' -> ' . $resolution : '')
                                    );
                                }
                                if (($mergedStream['streamInfo']['audio']['@attributes']['decision'] ?? '') === 'transcode') {
                                    $sourceCodec = $video['TranscodeSession']['@attributes']['sourceAudioCodec'] ?? '';
                                    $targetCodec = $video['TranscodeSession']['@attributes']['audioCodec'] ?? '';
                                    if ($sourceCodec !== '' || $targetCodec !== '') {
                                        $mergedStream['streamInfo']['audio']['@attributes']['decision'] .= ' (' . $sourceCodec . ' -> ' . $targetCodec . ')';
                                    }
                                }
                            }

                            $mergedStreams[] = $mergedStream;
                        }
                    }
                }
            }
            if (isset($streams['Track'])) {
                if (isset($streams['Track']) && isset($streams['Track']['@attributes'])) {
                    $streams['Track'] = [$streams['Track']];
                }
                foreach($streams['Track'] as $idx=>$audio) {
                    if (isset($audio['Media']['@attributes'])) {
                        $audio['Media'] = [$audio['Media']];
                    }
                    
                    foreach($audio['Media'] as $media) {
                        if (isset($media['Part']) && isset($media['Part']['@attributes'])) {
                            $media['Part'] = [$media['Part']];
                        }
                        foreach($media['Part'] as $part) {
                            if (isset($part['Stream']) && isset($part['Stream']['@attributes'])) {
                                $part['Stream'] = [$part['Stream']];
                            }
                            foreach ($part['Stream'] as $stream) {
                                if ($stream['@attributes']['selected'] === '1') {
                                    $title = $audio['@attributes']['title'] . ' - ' . $audio['@attributes']['originalTitle'];
                                    if (isset($audio['@attributes']['parentTitle']) && $audio['@attributes']['parentTitle'] !== '') {
                                        $title .= ' (' . $audio['@attributes']['parentTitle'] . ')';
                                    }
                                    $titleString = $title;
                                    $duration = $part['@attributes']['duration'];
                                    $lengthInSeconds = $duration / 1000;
                                    $lengthInMinutes = ceil($lengthInSeconds / 60 );
                                    $lengthSeconds = floor($lengthInSeconds%60);
                                    $lengthMinutes = floor(($lengthInSeconds%3600)/60);
                                    $lengthHours = floor(($lengthInSeconds%86400)/3600);
                                    $currentPosition = floatval((int)$audio['@attributes']['viewOffset']);
                                    $currentPositionInSeconds = $audio['@attributes']['viewOffset'] / 1000;
                                    $currentPositionInMinutes = ceil($currentPositionInSeconds / 60);
                                    $currentPositionSeconds = floor($currentPositionInSeconds%60);
                                    $currentPositionMinutes = floor(($currentPositionInSeconds%3600)/60);
                                    $currentPositionHours = floor(($currentPositionInSeconds%86400)/3600);
                                    $endSecondsFromNow = $lengthInSeconds - $currentPositionInSeconds;
                                    $endTime = date('h:i A', strtotime('+ ' . $endSecondsFromNow . ' seconds'));
                                    if ($display['time'] == '%R' && $display['date'] != '%c') {
                                        $endTime = date('H:i', strtotime('+ ' . $endSecondsFromNow . ' seconds'));
                                    }
                                    $addr = (string)$streams['shortHost'];
                                    $alias = '';
                                    $aliasKey = buildAliasKey($addr);
                                    if (isset($cfg[$aliasKey])) {
                                        $alias = $cfg[$aliasKey];
                                    }
                                    $mergedStream = [
                                        '@host' => $streams['@host'],
                                        'alias'=> $alias,
                                        'id' => $media['@attributes']['id'],
                                        'sessionId' => $audio['@attributes']['sessionKey'] ?? ($audio['Session']['@attributes']['id'] ?? null),
                                        'machineIdentifier' => $audio['@attributes']['machineIdentifier'] ?? '',
                                        'type' => 'audio',
                                        'product' => $audio['Player']['@attributes']['product'],
                                        'player' => $audio['Player']['@attributes']['title'] ?? $audio['Player']['@attributes']['product'],
                                        'title' => $title,
                                        'titleString' => $titleString,
                                        'key' => $audio['@attributes']['key'],
                                        'duration' => $duration,
                                        'artUrl' => '/plugins/plexstreamsplus/getImage.php?img=' . urlencode($audio['@attributes']['art']) . '&host=' . urlencode($streams['@host']),
                                        'thumbUrl' => '/plugins/plexstreamsplus/getImage.php?img=' .  urlencode($audio['@attributes']['grandparentThumb'] ?? $audio['@attributes']['thumb']) . '&host=' . urlencode($streams['@host']),
                                        'user' => $audio['User']['@attributes']['title'],
                                        'userAvatar' => $audio['User']['@attributes']['thumb'],
                                        'state' => $audio['Player']['@attributes']['state'],
                                        'stateIcon' => 'play',
                                        'length' => $duration,
                                        'lengthInSeconds' => $lengthInSeconds,
                                        'lengthInMinutes' => $lengthInMinutes,
                                        'lengthSeconds' => $lengthInSeconds,
                                        'lengthMinutes' => $lengthMinutes,
                                        'lengthHours' => $lengthHours,
                                        'currentPosition' => $currentPosition,
                                        'currentPositionInSeconds' =>  $currentPositionInSeconds,
                                        'currentPositionInMinutes' =>  $currentPositionInMinutes,
                                        'currentPositionHours' => $currentPositionHours,
                                        'currentPositionMinutes' => $currentPositionMinutes,
                                        'currentPositionSeconds' => $currentPositionSeconds,
                                        'percentPlayed' => $lengthInMinutes > 0 ? round(($currentPositionInMinutes/ $lengthInMinutes) * 100, 0) : '',
                                        'currentPositionDisplay' => str_pad($currentPositionHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($currentPositionSeconds, 2, '0', STR_PAD_LEFT),
                                        'lengthDisplay' => str_pad($lengthHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthMinutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($lengthSeconds, 2, '0', STR_PAD_LEFT),
                                        'location' => $audio['Session']['@attributes']['location'],
                                        'address' => $audio['Player']['@attributes']['address'],
                                        'bandwidth' => round((int)$audio['Session']['@attributes']['bandwidth'] / 1000, 1),
                                        'endTime' => $endTime,
                                        'streamInfo' => []
                                    ];
                                    if ($mergedStream['location'] === null) {
                                        if ($audio['Player']['@attributes']['local'] == "1") {
                                            $mergedStream['location'] = 'LAN';
                                        }
                                    }

                                    $loc = strtoupper((string)($mergedStream['location'] ?? 'UNKNOWN'));
                                    $mergedStream['locationDisplay'] = $loc . ' (' . $mergedStream['address'] . ($loc !== 'LAN' ? ' - ' .getGeo($mergedStream['address']) : '' ) . ')';

                                    if ($mergedStream['state'] === 'paused') {
                                        $mergedStream['stateIcon'] = 'pause';
                                    } else if ($mergedStream['state'] !== 'playing') {
                                        $mergedStream['stateIcon'] = 'buffer';
                                    }
                                    if (isset($part['@attributes']['decision'])) {
                                        $mergedStream['streamDecision'] = $part['@attributes']['decision'];
                                    } else {
                                        $mergedStream['streamDecision'] = 'Direct Play';
                                    }
                                    if ($mergedStream['streamDecision'] === 'directplay') {
                                        $mergedStream['streamDecision'] = 'Direct Play';
                                    }

                                    if (($stream['@attributes']['streamType'] ?? '') === '3') {
                                        $mergedStream['streamInfo']['subtitle'] = $stream;
                                        $mergedStream['streamInfo']['subtitle']['@attributes']['decision'] =
                                            $mergedStream['streamInfo']['subtitle']['@attributes']['decision'] ??
                                            ($mergedStream['streamInfo']['subtitle']['@attributes']['displayTitle'] ?? 'none');
                                    } else {
                                        $mergedStream['streamInfo']['audio'] = $stream;
                                        $mergedStream['streamInfo']['audio']['@attributes']['decision'] = $mergedStream['streamInfo']['audio']['@attributes']['decision'] ?? 'direct play';
                                    }

                                    $mergedStreams[] = $mergedStream;
                                }
                            }
                        }
                    }
                }
            }
        }

        // if (isset($scheduled) && isset($scheduled['@attributes'])) {
        //     $streams['Scheduled'] = [$streams['Scheduled']];
        //     foreach($streams['Scheduled'] as $scheduled) {

        //     }
        // }

        return applyPrivacyRules($mergedStreams, $cfg);
    }

?>
