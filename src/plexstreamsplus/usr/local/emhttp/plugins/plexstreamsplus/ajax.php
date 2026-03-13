<?php
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/common.php');
    
    header('Content-Type: application/json');
    global $display;

    $mergedStreams = [];
    $token = trim((string)($cfg['TOKEN'] ?? ''));
    if ($token !== '') {
        $hosts = trim((string)($cfg['HOST'] ?? ''));
        $customServers = trim((string)($cfg['CUSTOM_SERVERS'] ?? ''));
        if ($hosts !== '' || $customServers !== '') {
            $docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
            require_once "$docroot/webGui/include/Wrappers.php";
            extract(parse_plugin_cfg('dynamix',true));

            $streams = getStreams($cfg);
            $mergedStreams = mergeStreams($streams, $cfg);
            $allowTerminate = canViewerTerminateSessions($cfg);
            foreach ($mergedStreams as &$mergedStream) {
                if (is_array($mergedStream)) {
                    $mergedStream['canTerminate'] = $allowTerminate;
                    $mergedStream['viewerRole'] = getViewerRole();
                }
            }
            unset($mergedStream);
            
            if (isset($_REQUEST['dbg'])) {
                v_d($mergedStreams);
            }
            echo(json_encode($mergedStreams));
        } else {
            http_response_code(500);
            echo(json_encode(['error' => 'No Plex hosts configured']));
        }

    } else {
        http_response_code(500);
        echo(json_encode(['error' => 'No Plex token configured']));
    }
