<link type="text/css" rel="stylesheet" href="/plugins/plexstreamsplus/spinner.css">
<style>
    .caution {
        padding-left: 76px;
        margin: 16px -40px;
        padding: 16px 50px;
        background-color: rgb(254, 239, 227);
        color: rgb(191, 54, 12);
        display: block;
        font-weight: bolder;
        font-size: 14px;
    }

    .caution i {
        font-size: 15pt;
    }

    .caution .text {
        display: inline-block;
        vertical-align: 2px;
        padding-left: 7px;
    }

    #streams-container {
        display: block;
        width: 100%;
    }

    #streams-container > ul {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
    }

    .stream-container {
        flex: 1 1 520px;
        min-width: 420px;
        max-width: 520px;
        margin: 0;
    }

    .stream-card {
        background: #10161d;
        border: 1px solid #28313b;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
    }

    .stream-media {
        position: relative;
        display: flex;
        height: 250px;
        overflow: hidden;
        background: #0a0f14;
    }

    .stream-backdrop {
        position: absolute;
        inset: 0;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        filter: blur(8px);
        transform: scale(1.08);
    }

    .stream-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(6, 9, 12, 0.35) 0%, rgba(9, 13, 17, 0.72) 38%, rgba(9, 13, 17, 0.9) 100%);
    }

    .stream-poster {
        position: relative;
        z-index: 2;
        width: 116px;
        min-width: 116px;
        height: 174px;
        margin: 16px 12px 0 16px;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        border: 1px solid rgba(255, 255, 255, 0.14);
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.4);
    }

    .stream-details {
        position: relative;
        z-index: 2;
        flex: 1;
        min-width: 0;
        padding: 16px 14px 35px 0;
    }

    .stream-details .detail-list {
        display: flex;
        flex-direction: column;
        flex-wrap: nowrap;
        gap: 3px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .stream-details .detail-list li {
        display: flex;
        width: 100%;
        flex: 0 0 auto;
        align-items: baseline;
        gap: 6px;
        padding: 1px 0;
        margin-bottom: 0;
        line-height: 15px;
    }

    .detail-list .label {
        width: 78px;
        min-width: 78px;
        font-size: 10px;
        color: #a0abba;
        letter-spacing: 0.06em;
        font-weight: 700;
        text-transform: uppercase;
        text-align: right;
    }

    .detail-list .value {
        min-width: 0;
        flex: 1;
        font-size: 12px;
        color: #f3f7fb;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }

    .player-badge {
        position: absolute;
        z-index: 4;
        top: 10px;
        right: 10px;
        min-width: 58px;
        max-width: 90px;
        padding: 6px 10px;
        border-radius: 2px;
        color: #fdfdfd;
        background: linear-gradient(135deg, #2166b3, #24518c);
        font-size: 12px;
        font-weight: 700;
        text-align: center;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
        box-shadow: 0 5px 14px rgba(0, 0, 0, 0.38);
    }

    .progress-wrap {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 24px;
        z-index: 4;
        background: linear-gradient(0deg, rgba(0, 0, 0, 0.55), rgba(0, 0, 0, 0.05));
    }

    .progressBar {
        position: absolute;
        left: 0;
        bottom: 0;
        height: 4px;
        background-color: #f2a126;
    }

    .position {
        position: absolute;
        right: 8px;
        bottom: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #f3f7fb;
        text-align: right;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.75);
    }

    .stream-footer {
        border-top: 1px solid #27313d;
        background: #11171f;
        padding: 8px 12px 9px;
    }

    .footer-top {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .status {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 16px;
        min-width: 16px;
        color: #f6f8fa;
        font-size: 14px;
    }

    .stream-title-cell {
        min-width: 0;
        flex: 1;
    }

    .stream-title-link {
        display: block;
        color: #f8f9fa;
        text-decoration: none;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.25;
        font-family: "Open Sans", sans-serif;
    }

    .stream-title-link:hover {
        color: #ffffff;
        text-decoration: none;
    }

    .footer-bottom {
        margin-top: 5px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-width: 0;
    }

    .episode-meta-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        color: #d5dce7;
        font-size: 13px;
    }

    .episode-meta-wrap i {
        font-size: 12px;
        color: #c8d2df;
    }

    .episode-meta {
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }

    .session-user {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .session-user-avatar {
        width: 28px;
        height: 28px;
        min-width: 28px;
        border-radius: 50%;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        border: 1px solid rgba(255, 255, 255, 0.14);
    }

    .session-user-name {
        color: #d5dce7;
        font-size: 13px;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }

    #no-streams {
        text-align: center;
        font-style: italic;
    }

    .sb-overlay {
        backdrop-filter: blur(7px);
    }

    @media (max-width: 1350px) {
        .stream-container {
            min-width: 380px;
            max-width: 100%;
        }
    }

    @media (max-width: 1100px) {
        .stream-container {
            min-width: 100%;
        }
    }

    @media (max-width: 680px) {
        .stream-media {
            height: 240px;
        }

        .stream-poster {
            width: 94px;
            min-width: 94px;
            height: 142px;
            margin-left: 12px;
            margin-right: 10px;
        }

        .detail-list .label {
            width: 64px;
            min-width: 64px;
        }

        .detail-list .value {
            font-size: 11px;
        }

        .stream-title-link {
            font-size: 14px;
        }

        .session-user-name {
            max-width: 130px;
        }
    }
</style>
<script>
    function openBox(cmd, title, height, width, load, func, id) {
        var run = cmd.split('?')[0].substr(-4) === '.php' ? cmd : '/logging.htm?cmd=' + cmd + '&csrf_token=91E90CB5E22139F9';
        var options = {overlayOpacity: 0.90};
        Shadowbox.open({
            content: run,
            player: 'iframe',
            title: title,
            height: Math.min(height, screen.availHeight),
            width: Math.min(width, screen.availWidth),
            options: options
        });
    }
</script>
<?php
    $plugin = 'plexstreamsplus';
    $docroot = $docroot ?: $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
    $translations = file_exists("$docroot/webGui/include/Translations.php");
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/config.php');
    include('/usr/local/emhttp/plugins/plexstreamsplus/includes/common.php');

    if (!function_exists('h')) {
        function h($value) {
            return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    if (!function_exists('decisionLabel')) {
        function decisionLabel($value) {
            $value = trim(str_replace(['_', '-'], ' ', (string)$value));
            if ($value === '') {
                return 'None';
            }
            if (strcasecmp($value, 'directplay') === 0) {
                return 'Direct Play';
            }
            return ucwords($value);
        }
    }

    if (!function_exists('playerBadgeLabel')) {
        function playerBadgeLabel($product, $player) {
            $source = trim($product !== '' ? $product : $player);
            $source = preg_replace('/^Plex\s+(for|on)\s+/i', '', $source);
            if ($source === '') {
                return 'PLEX';
            }
            $tokens = preg_split('/\s+/', $source);
            $badge = strtoupper((string)($tokens[0] ?? 'PLEX'));
            if (strlen($badge) > 8) {
                $badge = substr($badge, 0, 8);
            }
            return $badge;
        }
    }

    if (!function_exists('streamDomId')) {
        function streamDomId($streamId) {
            return 'psplus-stream-' . substr(sha1((string)$streamId), 0, 16);
        }
    }

    if (!function_exists('episodeMetaLabel')) {
        function episodeMetaLabel($title, $type) {
            $title = trim((string)$title);
            if (strtolower((string)$type) !== 'video') {
                return 'Audio Session';
            }

            if (preg_match('/Season\s+([0-9]+)\s*-\s*([^\\(]+)/i', $title, $matches)) {
                return 'S' . $matches[1] . ' · ' . trim($matches[2]);
            }

            if (preg_match('/S([0-9]+)\s*E([0-9]+)/i', $title, $matches)) {
                return 'S' . $matches[1] . ' · E' . $matches[2];
            }

            if (preg_match('/\((20[0-9]{2}|19[0-9]{2})\)\s*$/', $title, $matches)) {
                return 'Movie · ' . $matches[1];
            }

            return 'Video Session';
        }
    }

    if ($translations) {
        $_SERVER['REQUEST_URI'] = 'plexstreamsplus';
        require_once "$docroot/webGui/include/Translations.php";
    } else {
        $noscript = true;
        require_once "$docroot/plugins/$plugin/includes/Legacy.php";
    }

    $mergedStreams = [];

    if (!empty($cfg['TOKEN'])) {
        $streams = getStreams($cfg);
        $mergedStreams = mergeStreams($streams, $cfg);

        if (count($mergedStreams) > 0) {
            echo '<div id="streams-container"><ul>';

            foreach ($mergedStreams as $stream) {
                $streamIdRaw = (string)($stream['id'] ?? '');
                $streamId = h(streamDomId($streamIdRaw));
                $streamDataId = h($streamIdRaw);
                $streamArt = h($stream['artUrl']);
                $streamThumb = h($stream['thumbUrl']);
                $streamUser = h($stream['user']);
                $streamUserAvatar = h($stream['userAvatar']);
                $streamAddress = h($stream['address'] ?? '');
                $streamAlias = h($stream['alias'] ?? '');
                $serverLabel = ($streamAlias !== '') ? $streamAlias : $streamAddress;
                $locationDisplayRaw = (string)($stream['locationDisplay'] ?? '');
                $locationDisplay = h($locationDisplayRaw);
                $streamBandwidthRaw = (string)($stream['bandwidth'] ?? '');
                $streamBandwidth = h($streamBandwidthRaw);

                $videoAttrs = $stream['streamInfo']['video']['@attributes'] ?? [];
                $audioAttrs = $stream['streamInfo']['audio']['@attributes'] ?? [];

                $streamProductRaw = trim((string)($stream['product'] ?? ($stream['player'] ?? 'Plex')));
                if ($streamProductRaw === '') {
                    $streamProductRaw = 'Plex';
                }
                $streamPlayerRaw = trim((string)($stream['player'] ?? $streamProductRaw));
                if ($streamPlayerRaw === '') {
                    $streamPlayerRaw = $streamProductRaw;
                }

                $qualityLabelRaw = (($stream['streamDecision'] ?? '') === 'transcode') ? 'Transcode' : 'Original';
                if ($streamBandwidthRaw !== '') {
                    $qualityLabelRaw .= ' (' . $streamBandwidthRaw . ' Mbps)';
                }

                $streamDecisionRaw = decisionLabel($stream['streamDecision'] ?? '');
                $videoDecisionRaw = isset($stream['streamInfo']['video']) ? decisionLabel($videoAttrs['decision'] ?? ($videoAttrs['displayTitle'] ?? 'Direct Play')) : 'N/A';
                if (($videoAttrs['displayTitle'] ?? '') !== '' && stripos($videoDecisionRaw, (string)$videoAttrs['displayTitle']) === false) {
                    $videoDecisionRaw .= ' (' . $videoAttrs['displayTitle'] . ')';
                }
                $audioDecisionRaw = isset($stream['streamInfo']['audio']) ? decisionLabel($audioAttrs['decision'] ?? 'Direct Play') : 'N/A';

                $duration = is_null($stream['duration']) ? '0' : h($stream['duration']);
                $percentPlayed = !is_null($stream['duration']) ? h($stream['percentPlayed']) : '0';
                $currentPositionDisplay = !is_null($stream['duration'])
                    ? '<span class="currentPositionHours">' . str_pad($stream['currentPositionHours'], 2, '0', STR_PAD_LEFT) . '</span>:<span class="currentPositionMinutes">' . str_pad($stream['currentPositionMinutes'], 2, '0', STR_PAD_LEFT) . '</span>:<span class="currentPositionSeconds">' . str_pad($stream['currentPositionSeconds'], 2, '0', STR_PAD_LEFT) . '</span> / ' . h($stream['lengthDisplay'])
                    : h(_('N/A'));

                $streamTitle = h($stream['title']);
                $streamState = h(ucwords($stream['state']));
                $streamStateIcon = h($stream['stateIcon']);
                $streamType = h($stream['type']);
                $episodeMeta = h(episodeMetaLabel((string)($stream['title'] ?? ''), (string)($stream['type'] ?? 'video')));
                $playerBadge = h(playerBadgeLabel($streamProductRaw, $streamPlayerRaw));
                $movieDetailUrl = '/plugins/plexstreamsplus/movieDetails.php?details=' . urlencode($stream['key']) . '&host=' . urlencode($stream['@host']);

                $titleMarkup = ($stream['type'] === 'video')
                    ? '<a class="stream-title-link" href="#" onclick="openBox(\'' . h($movieDetailUrl) . '\',\'Details\',600,900); return false;">' . $streamTitle . '</a>'
                    : '<span class="stream-title-link">' . $streamTitle . '</span>';

                echo '
                    <li class="stream-container" id="' . $streamId . '" data-stream-id="' . $streamDataId . '" data-stream-type="' . $streamType . '">
                        <article class="stream-card">
                            <div class="stream-media">
                                <div class="stream-backdrop" style="background-image:url(\'' . $streamArt . '\');"></div>
                                <div class="stream-overlay"></div>
                                <div class="stream-poster" style="background-image:url(\'' . $streamThumb . '\');"></div>
                                <div class="stream-details">
                                    <ul class="detail-list">
                                        <li><div class="label">' . _('Product') . '</div><div class="value product-value">' . h($streamProductRaw) . '</div></li>
                                        <li><div class="label">' . _('Quality') . '</div><div class="value quality-value">' . h($qualityLabelRaw) . '</div></li>
                                        <li><div class="label">' . _('Stream') . '</div><div class="value stream-value">' . h($streamDecisionRaw) . '</div></li>
                                        <li><div class="label">' . _('Video') . '</div><div class="value video-value">' . h($videoDecisionRaw) . '</div></li>
                                        <li><div class="label">' . _('Audio') . '</div><div class="value audio-value">' . h($audioDecisionRaw) . '</div></li>
                                        <li><div class="label">' . _('Location') . '</div><div class="value location-value" title="' . $locationDisplay . '">' . $locationDisplay . '</div></li>
                                    </ul>
                                </div>
                                <div class="player-badge" title="' . h($streamProductRaw) . '">' . $playerBadge . '</div>
                                <div class="progress-wrap">
                                    <div class="progressBar" duration="' . $duration . '" style="width:' . $percentPlayed . '%;"></div>
                                    <div class="position">' . $currentPositionDisplay . '</div>
                                </div>
                            </div>
                            <div class="stream-footer">
                                <div class="footer-top">
                                    <span class="status"><i class="fa fa-' . $streamStateIcon . '" title="' . $streamState . '"></i></span>
                                    <span class="stream-title-cell">' . $titleMarkup . '</span>
                                </div>
                                <div class="footer-bottom">
                                    <span class="episode-meta-wrap"><i class="fa fa-tv"></i><span class="episode-meta">' . $episodeMeta . '</span></span>
                                    <span class="session-user">
                                        <span class="session-user-avatar" title="' . $streamUser . '" style="background-image:url(\'' . $streamUserAvatar . '\');"></span>
                                        <span class="session-user-name">' . $streamUser . '</span>
                                    </span>
                                </div>
                            </div>
                        </article>
                    </li>
                ';
            }

            echo '</ul></div>';
        } else {
            echo '<p id="no-streams">' . _('There are currently no active streams') . '</p>';
        }
    } else {
        echo '<div class="caution"><i class="fa fa-exclamation-triangle"></i><div class="text">' . _('Please provide server details under Settings -> Network Services -> PlexStreams Plus or') . ' <a href="/Settings/PlexStreamsPlus">' . _('click here') . '</a></div></div>';
    }
?>
<script src="<?autov('/plugins/plexstreamsplus/js/plex.js')?>"></script>
<script>
    var title = $('title').html();
    $('title').html(title.split('/')[0] + '/PlexStreams Plus');
    updateFullStreamInfo();
    setInterval(updateFullStreamInfo, 5000);
</script>
