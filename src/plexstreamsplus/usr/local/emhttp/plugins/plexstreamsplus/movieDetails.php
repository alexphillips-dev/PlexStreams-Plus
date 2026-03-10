
<style>
body {
    padding: 25px;
}

.roles {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
}

.role {
    width: 200px;
    height: 200px;
}

.role .avatar {
    background-position: center;
    border-radius: 50%;
    overflow: hidden;
    height: 75px;
    width: 75px;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

</style>
<?php
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/common.php');

    if (!function_exists('h')) {
        function h($value) {
            return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    if (!function_exists('normalizeMediaList')) {
        function normalizeMediaList($items) {
            if (!isset($items)) {
                return [];
            }
            if (isset($items['@attributes'])) {
                return [$items];
            }
            return is_array($items) ? $items : [];
        }
    }

    $token = trim((string)($cfg['TOKEN'] ?? ''));
    $detailsPath = urldecode((string)($_GET['details'] ?? ''));
    $host = urldecode((string)($_GET['host'] ?? ''));
    if ($token !== '' && $detailsPath !== '' && $host !== '') {
        $normalizedHost = normalizeHostUrl($host);
        $validDetailsPath = preg_match('#^/[A-Za-z0-9_./-]+$#', $detailsPath) === 1;
        if ($normalizedHost === null || !$validDetailsPath || !isConfiguredHost($normalizedHost, $cfg)) {
            echo('<p>Unable to load details for this item.</p>');
            return;
        }

        $url = appendTokenToUrl($normalizedHost . $detailsPath, $token);
        $details = getUrl($url);
        if ($details === false || !isset($details['Video'])) {
            echo('<p>Unable to load details for this item.</p>');
            return;
        }

        $video = $details['Video'];
        $videoAttr = $video['@attributes'] ?? [];
        $title = $videoAttr['title'] ?? 'Untitled';
        $directors = [];
        $genres = [];

        foreach (normalizeMediaList($video['Genre'] ?? null) as $genre) {
            if (isset($genre['@attributes']['tag'])) {
                $genres[] = $genre['@attributes']['tag'];
            }
        }
        foreach (normalizeMediaList($video['Director'] ?? null) as $director) {
            if (isset($director['@attributes']['tag'])) {
                $directors[] = $director['@attributes']['tag'];
            }
        }
        echo('
            <h1>' . h($title) .'</h1>
            <p>' . h($videoAttr['summary'] ?? '') . '</p><p>
            <strong>Year:</strong> ' .h($videoAttr['year'] ?? '') . '<br/>
        ');

        if (!empty($videoAttr['studio'])) {
            echo('<strong>Studio:</strong> ' . h($videoAttr['studio']) . '<br/>');
        }
        if (count($directors) > 0) {
            echo('<strong>Director:</strong> ' . h(implode(' / ', $directors)) .'<br/>');
        }
        if (count($genres) > 0) {
            echo('<strong>Genre:</strong> ' . h(implode(' / ', $genres)) . '<br/>');
        }
        echo('<strong>Rating:</strong> ' . h($videoAttr['contentRating'] ?? '') . '</p>');

        
        //echo('<div class="roles">');
        echo('<p>');
        if (isset($video['Role'])) {
            echo('<h2>Cast</h2>');
            foreach(normalizeMediaList($video['Role']) as $role) {
                echo(h($role['@attributes']['tag'] ?? '') . ' as ' . h($role['@attributes']['role'] ?? '') . '<br/>');
            //     $imageUrl = str_replace('http:', 'https:', $role['@attributes']['thumb']);
            //     echo('
            //         <div class="role">
            //             <div class="avatar" style="background-image:url(' .$imageUrl .');"></div>
            //             <div>' .$role['@attributes']['Tag']  . '</div>
            //         </div>');
            }
            echo('</p>');
        }
        //echo('</div>');
    }
