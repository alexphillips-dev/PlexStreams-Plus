<?php
    include('/usr/local/emhttp/plugins/plexstreams/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreams/includes/common.php');

    function image404() {
        header('HTTP/1.1 404 Not Found');
        exit;
    }

    $token = trim((string)($cfg['TOKEN'] ?? ''));
    $img = urldecode((string)($_GET['img'] ?? ''));
    $host = urldecode((string)($_REQUEST['host'] ?? ''));
    if ($token === '' || $img === '') {
        image404();
    }

    if (preg_match('#^https?://#i', $img) === 1) {
        $parts = parse_url($img);
        if ($parts === false || !isset($parts['host']) || !isPlexDomain($parts['host'])) {
            image404();
        }
        $url = $img;
    } else {
        $normalizedHost = normalizeHostUrl($host);
        if ($normalizedHost === null || !isConfiguredHost($normalizedHost, $cfg)) {
            image404();
        }
        if (substr($img, 0, 1) !== '/') {
            $img = '/' . $img;
        }
        $url = appendTokenToUrl($normalizedHost . $img, $token);
    }

    if (isset($_GET['dbg'])) {
        var_dump($url);
    }

    // Check if the client already has the requested item
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    $ch = buildCurlHandle($url, 15);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $out = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($out === false || $httpCode < 200 || $httpCode >= 400) {
        image404();
    }
    if (preg_match('#^image/(png|.*icon|jpe?g|gif|webp)$#i', $contentType) !== 1) {
        image404();
    }

    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=300');
    echo $out;
?>
