<link type="text/css" rel="stylesheet" href="/plugins/plexstreamsplus/spinner.css">
<style>
    #psplus-streams-root .caution {
        padding-left: 76px;
        margin: 16px -40px;
        padding: 16px 50px;
        background-color: rgb(254, 239, 227);
        color: rgb(191, 54, 12);
        display: block;
        font-weight: bolder;
        font-size: 14px;
    }

    #psplus-streams-root .caution i {
        font-size: 15pt;
    }

    #psplus-streams-root .caution .text {
        display: inline-block;
        vertical-align: 2px;
        padding-left: 7px;
    }

    #psplus-streams-root #streams-container {
        display: block;
        width: 100%;
    }

    #psplus-streams-root #streams-container > ul {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
    }

    #psplus-streams-root .stream-container {
        flex: 1 1 520px;
        min-width: 420px;
        max-width: 520px;
        margin: 0;
    }

    #psplus-streams-root .stream-card {
        background: #10161d;
        border: 1px solid #28313b;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
    }

    #psplus-streams-root .stream-container.psplus-focus-target .stream-card {
        border-color: #f2a126;
        box-shadow: 0 0 0 1px rgba(242, 161, 38, 0.8), 0 10px 24px rgba(0, 0, 0, 0.5);
        animation: psplus-focus-pulse 2.4s ease-out 1;
    }

    @keyframes psplus-focus-pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(242, 161, 38, 0.8), 0 10px 24px rgba(0, 0, 0, 0.5);
        }
        100% {
            box-shadow: 0 0 0 12px rgba(242, 161, 38, 0), 0 10px 24px rgba(0, 0, 0, 0.5);
        }
    }

    #psplus-streams-root .stream-media {
        position: relative;
        display: flex;
        height: 208px;
        overflow: hidden;
        background: #0a0f14;
    }

    #psplus-streams-root .stream-backdrop {
        position: absolute;
        inset: 0;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        filter: blur(8px);
        transform: scale(1.08);
    }

    #psplus-streams-root .stream-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(6, 9, 12, 0.35) 0%, rgba(9, 13, 17, 0.72) 38%, rgba(9, 13, 17, 0.9) 100%);
    }

    #psplus-streams-root .stream-poster {
        position: relative;
        z-index: 2;
        width: 110px;
        min-width: 110px;
        height: 166px;
        margin: 9px 12px 0 12px;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        border: 1px solid rgba(255, 255, 255, 0.14);
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.4);
    }

    #psplus-streams-root .stream-details {
        position: relative;
        z-index: 2;
        flex: 1;
        min-width: 0;
        padding: 10px 12px 24px 0;
    }

    #psplus-streams-root .stream-details .detail-list {
        display: flex;
        flex-direction: column;
        flex-wrap: nowrap;
        gap: 2px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    #psplus-streams-root .stream-details .detail-list li {
        display: flex;
        width: 100%;
        flex: 0 0 auto;
        align-items: baseline;
        gap: 6px;
        padding: 0;
        margin-bottom: 0;
        line-height: 14px;
    }

    #psplus-streams-root .detail-list .label {
        width: 78px;
        min-width: 78px;
        font-size: 10px;
        color: #a0abba;
        letter-spacing: 0.06em;
        font-weight: 700;
        text-transform: uppercase;
        text-align: right;
    }

    #psplus-streams-root .detail-list .value {
        min-width: 0;
        flex: 1;
        font-size: 12px;
        color: #f3f7fb;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }

    #psplus-streams-root .player-badge {
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

    #psplus-streams-root .progress-wrap {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 18px;
        z-index: 4;
        background: linear-gradient(0deg, rgba(0, 0, 0, 0.55), rgba(0, 0, 0, 0.05));
    }

    #psplus-streams-root .progressBar {
        position: absolute;
        left: 0;
        bottom: 0;
        height: 3px;
        background-color: #f2a126;
    }

    #psplus-streams-root .position {
        position: absolute;
        right: 8px;
        bottom: 3px;
        font-size: 10px;
        font-weight: 600;
        color: #f3f7fb;
        text-align: right;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.75);
    }

    #psplus-streams-root .stream-footer {
        border-top: 1px solid #27313d;
        background: #11171f;
        padding: 6px 11px 7px;
    }

    #psplus-streams-root .footer-top {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    #psplus-streams-root .status {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 16px;
        min-width: 16px;
        color: #f6f8fa;
        font-size: 14px;
    }

    #psplus-streams-root .stream-title-cell {
        min-width: 0;
        flex: 1;
    }

    #psplus-streams-root .stream-title-link {
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

    #psplus-streams-root .stream-title-link:hover {
        color: #ffffff;
        text-decoration: none;
    }

    #psplus-streams-root .footer-bottom {
        margin-top: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-width: 0;
    }

    #psplus-streams-root .episode-meta-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        color: #d5dce7;
        font-size: 13px;
    }

    #psplus-streams-root .episode-meta-wrap i {
        font-size: 12px;
        color: #c8d2df;
    }

    #psplus-streams-root .episode-meta {
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }

    #psplus-streams-root .session-user {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    #psplus-streams-root .session-user-avatar {
        width: 28px;
        height: 28px;
        min-width: 28px;
        border-radius: 50%;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        border: 1px solid rgba(255, 255, 255, 0.14);
    }

    #psplus-streams-root .session-user-name {
        color: #d5dce7;
        font-size: 13px;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }

    #psplus-streams-root #no-streams {
        text-align: center;
        font-style: italic;
    }

    .sb-overlay {
        backdrop-filter: blur(7px);
    }

    @media (max-width: 1350px) {
        #psplus-streams-root .stream-container {
            min-width: 380px;
            max-width: 100%;
        }
    }

    @media (max-width: 1100px) {
        #psplus-streams-root .stream-container {
            min-width: 100%;
        }
    }

    @media (max-width: 680px) {
        #psplus-streams-root .stream-media {
            height: 196px;
        }

        #psplus-streams-root .stream-poster {
            width: 90px;
            min-width: 90px;
            height: 136px;
            margin-top: 8px;
            margin-left: 10px;
            margin-right: 10px;
        }

        #psplus-streams-root .detail-list .label {
            width: 64px;
            min-width: 64px;
        }

        #psplus-streams-root .detail-list .value {
            font-size: 11px;
        }

        #psplus-streams-root .stream-title-link {
            font-size: 14px;
        }

        #psplus-streams-root .session-user-name {
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
<div id="psplus-streams-root">
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

    if (!function_exists('streamSessionKey')) {
        function streamSessionKey($stream) {
            $host = trim((string)($stream['@host'] ?? ''));
            $id = trim((string)($stream['id'] ?? ''));
            if ($host !== '' && $id !== '') {
                return $host . '::' . $id;
            }
            return $id !== '' ? $id : $host;
        }
    }

    if (!function_exists('episodeMetaLabel')) {
        function episodeMetaLabel($title, $type) {
            $title = trim((string)$title);
            if (strtolower((string)$type) !== 'video') {
                return 'Audio Session';
            }

            if (preg_match('/Season\s+([0-9]+)\s*-\s*([^\\(]+)/i', $title, $matches)) {
                return 'S' . $matches[1] . ' - ' . trim($matches[2]);
            }

            if (preg_match('/S([0-9]+)\s*E([0-9]+)/i', $title, $matches)) {
                return 'S' . $matches[1] . ' - E' . $matches[2];
            }

            if (preg_match('/\((20[0-9]{2}|19[0-9]{2})\)\s*$/', $title, $matches)) {
                return 'Movie - ' . $matches[1];
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
                $streamSessionKeyRaw = streamSessionKey($stream);
                $streamId = h(streamDomId($streamSessionKeyRaw));
                $streamDataId = h($streamSessionKeyRaw);
                $streamLegacyDataId = h($streamIdRaw);
                $streamArt = h($stream['artUrl']);
                $streamThumb = h($stream['thumbUrl']);
                $streamUser = h($stream['user']);
                $streamUserAvatar = h($stream['userAvatar']);
                $locationDisplayRaw = (string)($stream['locationDisplay'] ?? '');
                $locationDisplay = h($locationDisplayRaw);
                $streamBandwidthRaw = (string)($stream['bandwidth'] ?? '');

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
                    <li class="stream-container" id="' . $streamId . '" data-stream-id="' . $streamDataId . '" data-stream-legacy-id="' . $streamLegacyDataId . '" data-stream-type="' . $streamType . '">
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
        echo '<div class="caution"><i class="fa fa-exclamation-triangle"></i><div class="text">' . _('Please provide server details under Settings -> User Utilities -> PlexStreams Plus or') . ' <a href="/Settings/PlexStreamsPlus">' . _('click here') . '</a></div></div>';
    }
?>
</div>
<script src="<?autov('/plugins/plexstreamsplus/js/plex.js')?>"></script>
<script>
    var pageTitle = $('title').html();
    $('title').html(pageTitle.split('/')[0] + '/PlexStreams Plus');
    plexStreamsPlusStartPolling('streams_page', function() {
        return updateFullStreamInfo('streams_page');
    });
</script>



