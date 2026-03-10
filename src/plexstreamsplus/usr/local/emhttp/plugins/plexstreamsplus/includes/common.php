<?php
    if (isset($GLOBALS['unRaidSettings'])) {
        define('OS_VERSION', 'Unraid ' . $GLOBALS['unRaidSettings']['version']);
    }
    define('PLUGIN_VERSION', '2026.03.09.25');

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

    function buildCurlHandle($url, $timeout = 30) {
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

        return $ch;
    }

    function appendTokenToUrl($url, $token) {
        if ((string)$token === '') {
            return $url;
        }

        return $url . (strpos($url, '?') !== false ? '&' : '?') . 'X-Plex-Token=' . urlencode($token);
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
        return getUrl($combined);
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

        return $mergedStreams;
    }

?>
