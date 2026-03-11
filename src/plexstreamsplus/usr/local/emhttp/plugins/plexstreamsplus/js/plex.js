var plexStreamsPlusServerList = [];
var plexStreamsPlusPollers = {};
var plexStreamsPlusPollState = {};
var plexStreamsPlusLiveClockTicker = null;
var plexStreamsPlusDashboardStatusTicker = null;
var plexStreamsPlusFocusStreamKey = null;
var plexStreamsPlusFocusApplied = false;
var PLEXSTREAMSPLUS_WIDGET_STALE_MS = 45000;
var plexStreamsPlusSettingsState = {
    customServersValid: true
};

function safeText(value) {
    if (value === undefined || value === null) {
        return '';
    }
    return String(value);
}

function plexStreamsPlusEscapeHtml(value) {
    return safeText(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function streamServerName(stream) {
    return safeText(stream.alias || stream.address || 'Unknown Server');
}

function streamSessionKey(stream) {
    var host = safeText(stream && (stream['@host'] || stream.host));
    var id = safeText(stream && stream.id);
    if (host && id) {
        return host + '::' + id;
    }
    return id || host || '';
}

function plexStreamsPlusParseClockToSeconds(clockValue) {
    var raw = safeText(clockValue).trim();
    if (!raw) {
        return 0;
    }

    var parts = raw.split(':');
    if (parts.length === 3) {
        return plexStreamsPlusToSeconds(parts[0], parts[1], parts[2]);
    }
    if (parts.length === 2) {
        return (Number(parts[0]) || 0) * 60 + (Number(parts[1]) || 0);
    }
    return Number(parts[0]) || 0;
}

function plexStreamsPlusShouldShowHours(durationSeconds, currentSeconds) {
    return (Number(durationSeconds) || 0) >= 3600 || (Number(currentSeconds) || 0) >= 3600;
}

function plexStreamsPlusFormatClockLabel(totalSeconds, showHours) {
    var bounded = Math.max(0, Math.floor(Number(totalSeconds) || 0));
    var hours = Math.floor(bounded / 3600);
    var minutes = Math.floor((bounded % 3600) / 60);
    var seconds = Math.floor(bounded % 60);
    if (showHours || hours > 0) {
        return String(hours) + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }
    return String(Math.floor(bounded / 60)) + ':' + String(seconds).padStart(2, '0');
}

function streamTimeHtml(stream, includeEndTime, smartFormat) {
    if (stream.currentPositionHours === null || stream.currentPositionHours === undefined) {
        return 'N/A';
    }

    var currentSeconds = plexStreamsPlusToSeconds(stream.currentPositionHours, stream.currentPositionMinutes, stream.currentPositionSeconds);
    var durationSeconds = plexStreamsPlusParseClockToSeconds(stream.lengthDisplay);
    if (smartFormat) {
        var showHours = plexStreamsPlusShouldShowHours(durationSeconds, currentSeconds);
        var currentLabel = plexStreamsPlusFormatClockLabel(currentSeconds, showHours);
        var durationLabel = plexStreamsPlusFormatClockLabel(durationSeconds, showHours);
        return '<span class="currentPositionText">' + currentLabel + '</span> / <span class="lengthText">' + durationLabel + '</span>';
    }

    var endTime = includeEndTime ? ' (<span class="endTime">' + plexStreamsPlusEscapeHtml(stream.endTime) + '</span>)' : '';
    return '<span class="currentPositionHours">' + stream.currentPositionHours.toString().padStart(2, 0) + '</span>:' +
        '<span class="currentPositionMinutes">' + stream.currentPositionMinutes.toString().padStart(2, 0) + '</span>:' +
        '<span class="currentPositionSeconds">' + stream.currentPositionSeconds.toString().padStart(2, 0) + '</span> / ' +
        plexStreamsPlusEscapeHtml(stream.lengthDisplay) + endTime;
}

function plexStreamsPlusResetPollState(context) {
    plexStreamsPlusPollState[context] = {
        idleStreak: 0,
        errorStreak: 0,
        lastCount: 0,
        lastAttemptAt: 0,
        lastSuccessAt: 0
    };
    return plexStreamsPlusPollState[context];
}

function plexStreamsPlusPollStateFor(context) {
    if (!plexStreamsPlusPollState[context]) {
        return plexStreamsPlusResetPollState(context);
    }
    return plexStreamsPlusPollState[context];
}

function plexStreamsPlusMarkPoll(context, streamCount, failed) {
    var state = plexStreamsPlusPollStateFor(context);
    var now = Date.now();
    state.lastAttemptAt = now;
    if (failed) {
        state.errorStreak += 1;
        state.lastCount = 0;
        return;
    }

    state.errorStreak = 0;
    state.lastCount = Number(streamCount || 0);
    state.lastSuccessAt = now;
    if (state.lastCount > 0) {
        state.idleStreak = 0;
    } else {
        state.idleStreak += 1;
    }
}

function plexStreamsPlusEnsureLiveClockTicker() {
    if (plexStreamsPlusLiveClockTicker) {
        return;
    }

    plexStreamsPlusLiveClockTicker = setInterval(function() {
        $('[data-psplus-live-time="1"]').each(function() {
            plexStreamsPlusRenderLiveTime($(this), Date.now());
        });
    }, 1000);
}

function plexStreamsPlusEnsureDashboardStatusTicker() {
    if (plexStreamsPlusDashboardStatusTicker) {
        return;
    }

    plexStreamsPlusDashboardStatusTicker = setInterval(function() {
        Object.keys(plexStreamsPlusPollState).forEach(function(context) {
            if (safeText(context).indexOf('dashboard_') === 0) {
                plexStreamsPlusRenderDashboardRefreshState(context);
            }
        });
    }, 1000);
}

function plexStreamsPlusDashboardRoot(context) {
    var $contextRoot = $('.psplus-dashboard-widget[data-psplus-context="' + safeText(context) + '"]');
    if ($contextRoot.length > 0) {
        return $contextRoot.first();
    }

    // If context-aware widgets exist, never fall back across contexts.
    var $contextAware = $('.psplus-dashboard-widget[data-psplus-context]');
    if ($contextAware.length > 0) {
        return $();
    }

    var $fallback = $('.psplus-dashboard-widget');
    if ($fallback.length > 0) {
        return $fallback.first();
    }
    return $();
}

function plexStreamsPlusDashboardFind(context, selector) {
    var $root = plexStreamsPlusDashboardRoot(context);
    if ($root.length > 0) {
        return $root.find(selector);
    }
    return $();
}

function plexStreamsPlusFormatClockTime(timestamp) {
    var time = new Date(Number(timestamp) || Date.now());
    var hours = String(time.getHours()).padStart(2, '0');
    var minutes = String(time.getMinutes()).padStart(2, '0');
    var seconds = String(time.getSeconds()).padStart(2, '0');
    return hours + ':' + minutes + ':' + seconds;
}

function plexStreamsPlusFormatSeconds(totalSeconds) {
    var bounded = Math.max(0, Number(totalSeconds) || 0);
    var hours = Math.floor(bounded / 3600);
    var minutes = Math.floor((bounded % 3600) / 60);
    var seconds = Math.floor(bounded % 60);
    return {
        hours: String(hours).padStart(2, '0'),
        minutes: String(minutes).padStart(2, '0'),
        seconds: String(seconds).padStart(2, '0')
    };
}

function plexStreamsPlusToSeconds(hours, minutes, seconds) {
    return (Number(hours) || 0) * 3600 + (Number(minutes) || 0) * 60 + (Number(seconds) || 0);
}

function plexStreamsPlusSyncLiveNode(node, stream) {
    var $node = $(node);

    if (!stream.duration) {
        node.psplusLiveState = null;
        $node.removeAttr('data-psplus-live-time');
        $node.find('.position').html(streamPositionHtml(stream));
        if (stream.endTime) {
            $node.find('.endTime').text(stream.endTime);
        }
        return;
    }

    var currentSeconds = plexStreamsPlusToSeconds(stream.currentPositionHours, stream.currentPositionMinutes, stream.currentPositionSeconds);
    var lengthParts = safeText(stream.lengthDisplay).split(':');
    var durationSeconds = lengthParts.length === 3
        ? plexStreamsPlusToSeconds(lengthParts[0], lengthParts[1], lengthParts[2])
        : Math.floor((Number(stream.duration) || 0) / 1000);
    if (!durationSeconds || durationSeconds < currentSeconds) {
        durationSeconds = currentSeconds;
    }

    node.psplusLiveState = {
        syncAt: Date.now(),
        positionSeconds: currentSeconds,
        durationSeconds: durationSeconds,
        state: safeText(stream.state),
        endTime: safeText(stream.endTime)
    };

    $node.attr('data-psplus-live-time', '1');
    plexStreamsPlusRenderLiveTime($node, Date.now());
}

function plexStreamsPlusRenderLiveTime(nodeOrContainer, nowMs) {
    var $node = nodeOrContainer && nodeOrContainer.jquery ? nodeOrContainer : $(nodeOrContainer);
    if ($node.length === 0) {
        return;
    }

    var domNode = $node[0];
    var state = domNode.psplusLiveState;
    if (!state) {
        return;
    }

    var now = Number(nowMs) || Date.now();
    var elapsed = 0;
    if (safeText(state.state).toLowerCase() === 'playing') {
        elapsed = Math.floor((now - state.syncAt) / 1000);
    }

    var liveSeconds = state.positionSeconds + Math.max(0, elapsed);
    if (state.durationSeconds > 0) {
        liveSeconds = Math.min(state.durationSeconds, liveSeconds);
    }

    var smartMode = safeText($node.attr('data-smart-time')) === '1';
    if (smartMode) {
        var smartShowHours = plexStreamsPlusShouldShowHours(state.durationSeconds, liveSeconds);
        var liveLabel = plexStreamsPlusFormatClockLabel(liveSeconds, smartShowHours);
        var durationLabel = plexStreamsPlusFormatClockLabel(state.durationSeconds, smartShowHours);
        var $currentText = $node.find('.currentPositionText');
        var $lengthText = $node.find('.lengthText');
        if ($currentText.length > 0 && $lengthText.length > 0) {
            $currentText.text(liveLabel);
            $lengthText.text(durationLabel);
        } else {
            $node.find('.plexstream-time, .position').first().html('<span class="currentPositionText">' + liveLabel + '</span> / <span class="lengthText">' + durationLabel + '</span>');
        }
        return;
    }

    var hms = plexStreamsPlusFormatSeconds(liveSeconds);
    var $hours = $node.find('.currentPositionHours');
    var $minutes = $node.find('.currentPositionMinutes');
    var $seconds = $node.find('.currentPositionSeconds');

    if ($hours.length === 0 || $minutes.length === 0 || $seconds.length === 0) {
        var durationLabel = safeText(state.durationSeconds > 0 ? plexStreamsPlusFormatSeconds(state.durationSeconds).hours + ':' + plexStreamsPlusFormatSeconds(state.durationSeconds).minutes + ':' + plexStreamsPlusFormatSeconds(state.durationSeconds).seconds : safeText($node.find('.position').text().split('/')[1] || '00:00:00').trim());
        var html = '<span class="currentPositionHours">' + hms.hours + '</span>:' +
            '<span class="currentPositionMinutes">' + hms.minutes + '</span>:' +
            '<span class="currentPositionSeconds">' + hms.seconds + '</span> / ' + plexStreamsPlusEscapeHtml(durationLabel);
        var $position = $node.find('.position');
        if ($position.length > 0) {
            $position.html(html);
            $hours = $node.find('.currentPositionHours');
            $minutes = $node.find('.currentPositionMinutes');
            $seconds = $node.find('.currentPositionSeconds');
        }
    }

    if ($hours.length > 0 && $minutes.length > 0 && $seconds.length > 0) {
        $hours.text(hms.hours);
        $minutes.text(hms.minutes);
        $seconds.text(hms.seconds);
    }

    if (state.endTime) {
        $node.find('.endTime').text(state.endTime);
    }
}

function plexStreamsPlusInitDashboardInteractions(context) {
    var $root = plexStreamsPlusDashboardRoot(context);
    if ($root.length === 0 || $root.data('psplusWidgetBound')) {
        return;
    }

    $root.data('psplusWidgetBound', true);
    $root.on('click', '.psplus-dashboard-row', function(event) {
        if ($(event.target).closest('a,button,input,label').length > 0) {
            return;
        }
        var key = safeText($(this).attr('data-stream-key'));
        if (key) {
            window.location.href = '/Tools/PlexStreamsPlusTools/Streams?focus=' + encodeURIComponent(key);
        }
    });
    $root.on('keydown', '.psplus-dashboard-row', function(event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        event.preventDefault();
        var key = safeText($(this).attr('data-stream-key'));
        if (key) {
            window.location.href = '/Tools/PlexStreamsPlusTools/Streams?focus=' + encodeURIComponent(key);
        }
    });
}

function plexStreamsPlusRenderDashboardSummary(context, streams) {
    var summary = {
        streams: 0,
        direct: 0,
        transcode: 0,
        lanMbps: 0,
        wanMbps: 0
    };

    (streams || []).forEach(function(stream) {
        summary.streams += 1;

        var decision = safeText(stream.streamDecision).toLowerCase();
        if (decision.indexOf('transcode') !== -1) {
            summary.transcode += 1;
        } else {
            summary.direct += 1;
        }

        var bandwidth = Number(stream.bandwidth);
        if (!isNaN(bandwidth)) {
            var location = safeText(stream.location).toUpperCase();
            if (location.indexOf('LAN') !== -1 || location.indexOf('LOCAL') !== -1) {
                summary.lanMbps += bandwidth;
            } else {
                summary.wanMbps += bandwidth;
            }
        }
    });

    var $root = plexStreamsPlusDashboardRoot(context);
    if ($root.length === 0) {
        return;
    }

    $root.find('.psplus-chip-value[data-chip="streams"]').text(summary.streams);
    $root.find('.psplus-chip-value[data-chip="direct"]').text(summary.direct);
    $root.find('.psplus-chip-value[data-chip="transcode"]').text(summary.transcode);
    $root.find('.psplus-chip-value[data-chip="lan"]').text(summary.lanMbps.toFixed(1));
    $root.find('.psplus-chip-value[data-chip="wan"]').text(summary.wanMbps.toFixed(1));
}

function plexStreamsPlusRenderDashboardRefreshState(context) {
    var $root = plexStreamsPlusDashboardRoot(context);
    if ($root.length === 0) {
        return;
    }

    var state = plexStreamsPlusPollStateFor(context);
    var $refresh = $root.find('.psplus-refresh-state');
    var $text = $root.find('.psplus-refresh-text');
    if ($refresh.length === 0 || $text.length === 0) {
        return;
    }

    $refresh.removeClass('is-pending is-ok is-stale is-error');
    if (!state.lastSuccessAt) {
        $refresh.addClass('is-pending');
        $text.text(_('Waiting for first update...'));
        return;
    }

    var ageMs = Date.now() - state.lastSuccessAt;
    var ageSeconds = Math.max(0, Math.floor(ageMs / 1000));
    var label = _('Last updated') + ': ' + plexStreamsPlusFormatClockTime(state.lastSuccessAt) + ' (' + ageSeconds + 's ' + _('ago') + ')';
    if (state.errorStreak > 0) {
        $refresh.addClass('is-error');
        $text.text(label + ' - ' + _('retrying'));
        return;
    }

    if (ageMs > PLEXSTREAMSPLUS_WIDGET_STALE_MS) {
        $refresh.addClass('is-stale');
        $text.text(_('Data may be stale') + ' - ' + label);
        return;
    }

    $refresh.addClass('is-ok');
    $text.text(label);
}

function plexStreamsPlusBuildHostBreakdown(hostStreams) {
    var lines = [];
    for (var host in hostStreams) {
        if (hostStreams.hasOwnProperty(host)) {
            lines.push('<span class="psplus-host-chip"><strong>' + plexStreamsPlusEscapeHtml(host) + '</strong>: ' + hostStreams[host] + '</span>');
        }
    }
    return lines.join('');
}

function plexStreamsPlusGetQueryParam(name) {
    var search = safeText(window.location.search);
    if (!search || search.length < 2) {
        return '';
    }
    var params = new URLSearchParams(search);
    return safeText(params.get(name) || '');
}

function plexStreamsPlusInitFocusTarget() {
    if (plexStreamsPlusFocusStreamKey !== null) {
        return;
    }
    plexStreamsPlusFocusStreamKey = plexStreamsPlusGetQueryParam('focus');
    if (!plexStreamsPlusFocusStreamKey) {
        plexStreamsPlusFocusStreamKey = plexStreamsPlusGetQueryParam('stream');
    }
}

function plexStreamsPlusTryFocusStream($container, streamKey) {
    if (!plexStreamsPlusFocusStreamKey || plexStreamsPlusFocusApplied) {
        return;
    }
    if (safeText(streamKey) !== safeText(plexStreamsPlusFocusStreamKey)) {
        return;
    }

    plexStreamsPlusFocusApplied = true;
    $container.addClass('psplus-focus-target');
    if ($container.length > 0 && $container[0] && typeof $container[0].scrollIntoView === 'function') {
        $container[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    setTimeout(function() {
        $container.removeClass('psplus-focus-target');
    }, 7000);
}

function plexStreamsPlusNextPollDelay(context) {
    var state = plexStreamsPlusPollStateFor(context);
    var minDelay = 5000;
    var maxDelay = 30000;

    if (state.errorStreak > 0) {
        return Math.min(maxDelay, minDelay + (state.errorStreak * 5000));
    }
    if (state.lastCount > 0) {
        return minDelay;
    }
    return Math.min(maxDelay, minDelay + (state.idleStreak * 5000));
}

function plexStreamsPlusStopPolling(context) {
    if (plexStreamsPlusPollers[context]) {
        clearTimeout(plexStreamsPlusPollers[context]);
        delete plexStreamsPlusPollers[context];
    }
}

function plexStreamsPlusStartPolling(context, updater) {
    if (typeof updater !== 'function') {
        return;
    }

    if (plexStreamsPlusPollers[context]) {
        return;
    }

    plexStreamsPlusEnsureLiveClockTicker();
    if (safeText(context).indexOf('dashboard_') === 0) {
        plexStreamsPlusEnsureDashboardStatusTicker();
        plexStreamsPlusInitDashboardInteractions(context);
        plexStreamsPlusRenderDashboardRefreshState(context);
    }

    plexStreamsPlusStopPolling(context);
    plexStreamsPlusResetPollState(context);

    var run = function() {
        var request = updater();
        if (!request || typeof request.always !== 'function') {
            plexStreamsPlusMarkPoll(context, 0, true);
            plexStreamsPlusPollers[context] = setTimeout(run, plexStreamsPlusNextPollDelay(context));
            return;
        }

        request.always(function() {
            plexStreamsPlusPollers[context] = setTimeout(run, plexStreamsPlusNextPollDelay(context));
        });
    };

    run();
}

function updateDashboardStreamsNew(pollContext) {
    var context = safeText(pollContext || 'dashboard_new');
    var $streamsHolder = plexStreamsPlusDashboardFind(context, '#plexstreamsplus_streams');
    var $count = plexStreamsPlusDashboardFind(context, '#plexstreamsplus_count');
    var $hostBreakdown = plexStreamsPlusDashboardFind(context, '#stream_count_container');
    return $.ajax('/plugins/plexstreamsplus/ajax.php').done(function(streams) {
        streams = $.isArray(streams) ? streams : [];
        var lastUpdate = Date.now();
        var hostStreams = {};

        $count.text(streams.length);
        plexStreamsPlusDashboardFind(context, '#retrieving_streams').remove();
        plexStreamsPlusRenderDashboardSummary(context, streams);

        if (streams.length > 0) {
            $streamsHolder.find('.no_streams').remove();
            streams.forEach(function(stream) {
                var streamKey = streamSessionKey(stream);
                var safeKey = plexStreamsPlusEscapeHtml(streamKey);
                var domId = streamDomId(streamKey);
                var hostName = streamServerName(stream);
                hostStreams[hostName] = (hostStreams[hostName] || 0) + 1;

                var $matches = $streamsHolder.children('.psplus-dashboard-row').filter(function() {
                    return safeText($(this).attr('data-stream-key')) === streamKey;
                });
                if ($matches.length > 1) {
                    $matches.slice(1).each(function() {
                        $(this).removeAttr('data-psplus-live-time');
                        this.psplusLiveState = null;
                        $(this).remove();
                    });
                    $matches = $matches.first();
                }

                var $container = $matches.first();
                if ($container.length === 0) {
                    $container = $('<div class="psplus-dashboard-row" role="link" tabindex="0" data-smart-time="1" data-stream-key="' + safeKey + '" id="' + plexStreamsPlusEscapeHtml(domId) + '">' +
                        '<span class="psplus-col-name"><p class="plexstream-title" title="' + plexStreamsPlusEscapeHtml(stream.titleString) + '">' + plexStreamsPlusEscapeHtml(stream.title) + '</p></span>' +
                        '<span class="psplus-col-status" style="text-align:center;"><i class="fa fa-' + plexStreamsPlusEscapeHtml(stream.stateIcon) + '" title="' + plexStreamsPlusEscapeHtml(stream.state) + '"></i></span>' +
                        '<span class="psplus-col-user"><p class="plexstream-user" title="' + plexStreamsPlusEscapeHtml(stream.user) + '">' + plexStreamsPlusEscapeHtml(stream.user) + '</p></span>' +
                        '<span class="plexstreamsplus-time-col psplus-col-time"><p class="plexstream-time">' + streamTimeHtml(stream, false, true) + '</p></span>' +
                    '</div>').appendTo($streamsHolder);
                }

                var node = $container[0];
                $container.attr('id', domId);
                $container.attr('data-stream-key', streamKey);
                $container.attr('data-smart-time', '1');
                $container.attr('updatedat', lastUpdate);
                $container.find('.plexstream-title').text(stream.title).attr('title', stream.titleString);
                $container.find('.psplus-col-status i').attr('class', 'fa fa-' + stream.stateIcon).attr('title', uCWord(stream.state));
                $container.find('.plexstream-user').text(stream.user).attr('title', stream.user);
                $container.find('.plexstream-time').html(streamTimeHtml(stream, false, true));
                updateDuration(node, stream);
                node.prevState = stream.state;
            });

            $hostBreakdown.html(plexStreamsPlusBuildHostBreakdown(hostStreams));
            $streamsHolder.children('.psplus-dashboard-row[updatedat]').each(function() {
                if (safeText($(this).attr('updatedat')) !== String(lastUpdate)) {
                    $(this).removeAttr('data-psplus-live-time');
                    this.psplusLiveState = null;
                    $(this).remove();
                }
            });
        } else {
            $hostBreakdown.html('');
            $streamsHolder.html('<div class="no_streams"><span class="w100"><p style="text-align:center;font-style:italic;font-size:13px;">' + _('There are currently no active streams') + '</p></span></div>');
        }

        plexStreamsPlusMarkPoll(context, streams.length, false);
        plexStreamsPlusRenderDashboardRefreshState(context);
    }).fail(function(jqXHR) {
        plexStreamsPlusMarkPoll(context, 0, true);
        plexStreamsPlusRenderDashboardSummary(context, []);
        plexStreamsPlusRenderDashboardRefreshState(context);
        $count.text('0');
        $hostBreakdown.html('');
        if (jqXHR.status == '500') {
            $streamsHolder.html('<span class="w100"><p style="text-align:center;font-style:italic;font-size:13px;">' + _('Please make sure you have') + ' <a href="/Settings/PlexStreamsPlus">' + _('setup') + '</a> ' + _('the plugin first') + '</p></span>');
        }
    });
}


function updateDashboardStreams(pollContext) {
    var context = safeText(pollContext || 'dashboard_legacy');
    var $streamsHolder = plexStreamsPlusDashboardFind(context, '#plexstreamsplus_streams');
    var $count = plexStreamsPlusDashboardFind(context, '#plexstreamsplus_count');
    var $hostBreakdown = plexStreamsPlusDashboardFind(context, '#stream_count_container');
    return $.ajax('/plugins/plexstreamsplus/ajax.php').done(function(streams) {
        streams = $.isArray(streams) ? streams : [];
        var lastUpdate = Date.now();
        var hostStreams = {};

        $count.text(streams.length);
        plexStreamsPlusDashboardFind(context, '#retrieving_streams').remove();
        plexStreamsPlusRenderDashboardSummary(context, streams);

        if (streams.length > 0) {
            $streamsHolder.find('.no_streams').remove();
            streams.forEach(function(stream) {
                var streamKey = streamSessionKey(stream);
                var safeKey = plexStreamsPlusEscapeHtml(streamKey);
                var domId = streamDomId(streamKey);
                var hostName = streamServerName(stream);
                hostStreams[hostName] = (hostStreams[hostName] || 0) + 1;

                var $matches = $streamsHolder.children('.psplus-dashboard-row').filter(function() {
                    return safeText($(this).attr('data-stream-key')) === streamKey;
                });
                if ($matches.length > 1) {
                    $matches.slice(1).each(function() {
                        $(this).removeAttr('data-psplus-live-time');
                        this.psplusLiveState = null;
                        $(this).remove();
                    });
                    $matches = $matches.first();
                }

                var $container = $matches.first();
                if ($container.length === 0) {
                    $container = $('<tr class="psplus-dashboard-row" role="link" tabindex="0" data-smart-time="1" data-stream-key="' + safeKey + '" style="display:table-row;" id="' + plexStreamsPlusEscapeHtml(domId) + '">' +
                        '<td width="40%" style="padding: 0px;"><p class="plexstream-title" title="' + plexStreamsPlusEscapeHtml(stream.titleString) + '">' + plexStreamsPlusEscapeHtml(stream.title) + '</p></td>' +
                        '<td align="center" style="padding: 0px;text-align:center;"><i class="fa fa-' + plexStreamsPlusEscapeHtml(stream.stateIcon) + '" title="' + plexStreamsPlusEscapeHtml(stream.state) + '"></i></td>' +
                        '<td align="center" style="padding: 0px;"><p class="plexstream-user" title="' + plexStreamsPlusEscapeHtml(stream.user) + '">' + plexStreamsPlusEscapeHtml(stream.user) + '</p></td>' +
                        '<td align="center" style="padding: 0px;text-align:right;"><p class="plexstream-time">' + streamTimeHtml(stream, false, true) + '</p></td>' +
                    '</tr>').appendTo($streamsHolder);
                }

                var node = $container[0];
                $container.attr('id', domId);
                $container.attr('data-stream-key', streamKey);
                $container.attr('data-smart-time', '1');
                $container.attr('updatedat', lastUpdate);
                $container.find('.plexstream-title').text(stream.title).attr('title', stream.titleString);
                $container.find('td:eq(1) i').attr('class', 'fa fa-' + stream.stateIcon).attr('title', uCWord(stream.state));
                $container.find('.plexstream-user').text(stream.user).attr('title', stream.user);
                $container.find('.plexstream-time').html(streamTimeHtml(stream, false, true));
                node.prevState = stream.state;
                updateDuration(node, stream);
            });

            $hostBreakdown.html(plexStreamsPlusBuildHostBreakdown(hostStreams));
            $streamsHolder.children('.psplus-dashboard-row[updatedat]').each(function() {
                if (safeText($(this).attr('updatedat')) !== String(lastUpdate)) {
                    $(this).removeAttr('data-psplus-live-time');
                    this.psplusLiveState = null;
                    $(this).remove();
                }
            });
        } else {
            $hostBreakdown.html('');
            $streamsHolder.html('<tr class="no_streams"><td colspan="4" align="center" style="padding: 0 0 0 0;"><p style="text-align:center;font-style:italic;">' + _('There are currently no active streams') + '</p></td></tr>');
        }

        plexStreamsPlusMarkPoll(context, streams.length, false);
        plexStreamsPlusRenderDashboardRefreshState(context);
    }).fail(function(jqXHR) {
        plexStreamsPlusMarkPoll(context, 0, true);
        plexStreamsPlusRenderDashboardSummary(context, []);
        plexStreamsPlusRenderDashboardRefreshState(context);
        $count.text('0');
        $hostBreakdown.html('');
        if (jqXHR.status == '500') {
            $streamsHolder.html('<tr><td colspan="4" align="center"><p style="text-align:center;font-style:italic;">' + _('Please make sure you have') + ' <a href="/Settings/PlexStreamsPlus">' + _('setup') + '</a> ' + _('the plugin first') + '</p></td></tr>');
        }
    });
}

function uCWord(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function decisionLabel(value) {
    var label = safeText(value).replace(/[_-]/g, ' ').trim();
    if (!label) {
        return 'None';
    }
    if (label.toLowerCase() === 'directplay') {
        return 'Direct Play';
    }
    return label.replace(/\b\w/g, function(letter) {
        return letter.toUpperCase();
    });
}

function streamAttr(stream, mediaKey, attrKey) {
    if (!stream || !stream.streamInfo || !stream.streamInfo[mediaKey]) {
        return '';
    }
    var media = stream.streamInfo[mediaKey];
    var attrs = media['@attributes'] || media;
    if (!attrs || attrs[attrKey] === undefined || attrs[attrKey] === null) {
        return '';
    }
    return safeText(attrs[attrKey]);
}

function streamDomId(streamId) {
    var rawId = safeText(streamId);
    var normalized = rawId.replace(/[^A-Za-z0-9_-]/g, '_');
    if (!normalized) {
        normalized = 'stream';
    }

    var hash = 0;
    for (var i = 0; i < rawId.length; i += 1) {
        hash = ((hash << 5) - hash) + rawId.charCodeAt(i);
        hash |= 0;
    }

    return 'psplus-stream-' + normalized.slice(0, 36) + '-' + (hash >>> 0).toString(16);
}

function streamContainersByDataId($scope, streamId) {
    var expectedId = safeText(streamId);
    return $scope.children('.stream-container').filter(function() {
        var $container = $(this);
        return safeText($container.attr('data-stream-id')) === expectedId ||
            safeText($container.attr('data-stream-legacy-id')) === expectedId;
    });
}

function streamProductName(stream) {
    return safeText(stream.product || stream.player || 'Plex');
}

function streamPlayerName(stream) {
    return safeText(stream.player || stream.product || 'Plex');
}

function streamBadgeLabel(stream) {
    var source = streamProductName(stream).replace(/^Plex\s+(for|on)\s+/i, '').trim();
    if (!source) {
        return 'PLEX';
    }
    var badge = source.split(/\s+/)[0].toUpperCase();
    return badge.length > 8 ? badge.slice(0, 8) : badge;
}

function streamQualityValue(stream) {
    var mode = safeText(stream.streamDecision).toLowerCase() === 'transcode' ? 'Transcode' : 'Original';
    var bandwidth = safeText(stream.bandwidth);
    return bandwidth ? mode + ' (' + bandwidth + ' Mbps)' : mode;
}

function streamContainerValue(stream) {
    var container = streamAttr(stream, 'video', 'container') || streamAttr(stream, 'audio', 'container');
    if (!container) {
        return safeText(stream.type).toLowerCase() === 'audio' ? 'Audio' : 'Unknown';
    }
    return decisionLabel(container);
}

function streamVideoValue(stream) {
    if (!stream.streamInfo || !stream.streamInfo.video) {
        return 'N/A';
    }
    var value = decisionLabel(streamAttr(stream, 'video', 'decision') || streamAttr(stream, 'video', 'displayTitle') || 'Direct Play');
    var displayTitle = streamAttr(stream, 'video', 'displayTitle');
    if (displayTitle && value.toLowerCase().indexOf(displayTitle.toLowerCase()) === -1) {
        value += ' (' + displayTitle + ')';
    }
    return value;
}

function streamAudioValue(stream) {
    if (!stream.streamInfo || !stream.streamInfo.audio) {
        return 'N/A';
    }
    return decisionLabel(streamAttr(stream, 'audio', 'decision') || 'Direct Play');
}

function streamSubtitleValue(stream) {
    if (!stream.streamInfo || !stream.streamInfo.subtitle) {
        return 'None';
    }
    var subtitle = streamAttr(stream, 'subtitle', 'displayTitle') ||
        streamAttr(stream, 'subtitle', 'title') ||
        streamAttr(stream, 'subtitle', 'decision');
    return decisionLabel(subtitle || 'None');
}

function streamEpisodeMeta(stream) {
    var streamType = safeText(stream.type).toLowerCase();
    if (streamType !== 'video') {
        return 'Audio Session';
    }

    var title = safeText(stream.title);
    var seasonMatch = title.match(/Season\s+(\d+)\s*-\s*([^\(]+)/i);
    if (seasonMatch) {
        return 'S' + seasonMatch[1] + ' - ' + seasonMatch[2].trim();
    }

    var episodeMatch = title.match(/S(\d+)\s*E(\d+)/i);
    if (episodeMatch) {
        return 'S' + episodeMatch[1] + ' - E' + episodeMatch[2];
    }

    var movieMatch = title.match(/\((20\d{2}|19\d{2})\)\s*$/);
    if (movieMatch) {
        return 'Movie - ' + movieMatch[1];
    }

    return 'Video Session';
}

function streamPositionHtml(stream) {
    if (stream.duration === null || stream.duration === undefined || stream.duration === '') {
        return 'N/A';
    }
    return '<span class="currentPositionHours">' + safeText(stream.currentPositionHours).toString().padStart(2, 0) + '</span>:' +
        '<span class="currentPositionMinutes">' + safeText(stream.currentPositionMinutes).toString().padStart(2, 0) + '</span>:' +
        '<span class="currentPositionSeconds">' + safeText(stream.currentPositionSeconds).toString().padStart(2, 0) + '</span> / ' +
        plexStreamsPlusEscapeHtml(stream.lengthDisplay);
}

function streamDetailUrl(stream) {
    return '/plugins/plexstreamsplus/movieDetails.php?details=' + encodeURIComponent(stream.key) + '&host=' + encodeURIComponent(stream['@host']);
}

function buildStreamTitleMarkup(stream) {
    var safeTitle = plexStreamsPlusEscapeHtml(stream.title);
    if (safeText(stream.type).toLowerCase() === 'video') {
        var url = streamDetailUrl(stream).replace(/'/g, "\\'");
        return '<a class="stream-title-link" href="#" onclick="openBox(\'' + url + '\',\'Details\',600,900); return false;">' + safeTitle + '</a>';
    }
    return '<span class="stream-title-link">' + safeTitle + '</span>';
}

function buildFullStreamCard(stream) {
    var streamIdRaw = safeText(stream.id);
    var streamKey = streamSessionKey(stream);
    var safeId = plexStreamsPlusEscapeHtml(streamDomId(streamKey));
    var safeStreamId = plexStreamsPlusEscapeHtml(streamKey);
    var safeType = plexStreamsPlusEscapeHtml(stream.type || '');
    var safeArt = plexStreamsPlusEscapeHtml(stream.artUrl);
    var safeThumb = plexStreamsPlusEscapeHtml(stream.thumbUrl);
    var safeUser = plexStreamsPlusEscapeHtml(stream.user);
    var safeUserAvatar = plexStreamsPlusEscapeHtml(stream.userAvatar);
    var safeLocation = plexStreamsPlusEscapeHtml(stream.locationDisplay || '');
    var safeState = plexStreamsPlusEscapeHtml(uCWord(stream.state));
    var safeStateIcon = plexStreamsPlusEscapeHtml(stream.stateIcon);
    var safeBadge = plexStreamsPlusEscapeHtml(streamBadgeLabel(stream));
    var safeProduct = plexStreamsPlusEscapeHtml(streamProductName(stream));
    var safeQuality = plexStreamsPlusEscapeHtml(streamQualityValue(stream));
    var safeStream = plexStreamsPlusEscapeHtml(decisionLabel(stream.streamDecision));
    var safeVideo = plexStreamsPlusEscapeHtml(streamVideoValue(stream));
    var safeAudio = plexStreamsPlusEscapeHtml(streamAudioValue(stream));
    var safeEpisode = plexStreamsPlusEscapeHtml(streamEpisodeMeta(stream));
    var positionHtml = streamPositionHtml(stream);
    var duration = plexStreamsPlusEscapeHtml(stream.duration || 0);
    var percentPlayed = plexStreamsPlusEscapeHtml(stream.percentPlayed || 0);
    var titleHtml = buildStreamTitleMarkup(stream);

    return '<li class="stream-container" id="' + safeId + '" data-stream-id="' + safeStreamId + '" data-stream-legacy-id="' + plexStreamsPlusEscapeHtml(streamIdRaw) + '" data-stream-type="' + safeType + '">' +
        '<article class="stream-card">' +
            '<div class="stream-media">' +
                '<div class="stream-backdrop" style="background-image:url(\'' + safeArt + '\');"></div>' +
                '<div class="stream-overlay"></div>' +
                '<div class="stream-poster" style="background-image:url(\'' + safeThumb + '\');"></div>' +
                '<div class="stream-details">' +
                    '<ul class="detail-list">' +
                        '<li><div class="label">' + _('Product') + '</div><div class="value product-value">' + safeProduct + '</div></li>' +
                        '<li><div class="label">' + _('Quality') + '</div><div class="value quality-value">' + safeQuality + '</div></li>' +
                        '<li><div class="label">' + _('Stream') + '</div><div class="value stream-value">' + safeStream + '</div></li>' +
                        '<li><div class="label">' + _('Video') + '</div><div class="value video-value">' + safeVideo + '</div></li>' +
                        '<li><div class="label">' + _('Audio') + '</div><div class="value audio-value">' + safeAudio + '</div></li>' +
                        '<li><div class="label">' + _('Location') + '</div><div class="value location-value" title="' + safeLocation + '">' + safeLocation + '</div></li>' +
                    '</ul>' +
                '</div>' +
                '<div class="player-badge" title="' + safeProduct + '">' + safeBadge + '</div>' +
                '<div class="progress-wrap">' +
                    '<div class="progressBar" duration="' + duration + '" style="width:' + percentPlayed + '%;"></div>' +
                    '<div class="position">' + positionHtml + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="stream-footer">' +
                '<div class="footer-top">' +
                    '<span class="status"><i class="fa fa-' + safeStateIcon + '" title="' + safeState + '"></i></span>' +
                    '<span class="stream-title-cell">' + titleHtml + '</span>' +
                '</div>' +
                '<div class="footer-bottom">' +
                    '<span class="episode-meta-wrap"><i class="fa fa-tv"></i><span class="episode-meta">' + safeEpisode + '</span></span>' +
                    '<span class="session-user">' +
                        '<span class="session-user-avatar" title="' + safeUser + '" style="background-image:url(\'' + safeUserAvatar + '\');"></span>' +
                        '<span class="session-user-name">' + safeUser + '</span>' +
                    '</span>' +
                '</div>' +
            '</div>' +
        '</article>' +
    '</li>';
}

function streamStaticSignature(stream) {
    return JSON.stringify({
        session: streamSessionKey(stream),
        id: safeText(stream.id),
        title: safeText(stream.title),
        key: safeText(stream.key),
        type: safeText(stream.type),
        artUrl: safeText(stream.artUrl),
        thumbUrl: safeText(stream.thumbUrl),
        user: safeText(stream.user),
        userAvatar: safeText(stream.userAvatar),
        product: streamProductName(stream),
        player: streamPlayerName(stream)
    });
}

function refreshStreamCardDynamic($container, stream) {
    var percentPlayed = stream.percentPlayed || 0;
    var location = safeText(stream.locationDisplay || '');

    $container.find('.status i').attr('class', 'fa fa-' + safeText(stream.stateIcon)).attr('title', uCWord(stream.state));
    $container.find('.quality-value').text(streamQualityValue(stream));
    $container.find('.stream-value').text(decisionLabel(stream.streamDecision));
    $container.find('.video-value').text(streamVideoValue(stream));
    $container.find('.audio-value').text(streamAudioValue(stream));
    $container.find('.location-value').text(location).attr('title', location);
    $container.find('.progressBar').css('width', percentPlayed + '%').attr('duration', safeText(stream.duration || 0));
}

function refreshStreamCard($container, stream) {
    var percentPlayed = stream.percentPlayed || 0;
    var location = safeText(stream.locationDisplay || '');
    var user = safeText(stream.user);

    $container.find('.stream-backdrop').css('background-image', 'url("' + safeText(stream.artUrl) + '")');
    $container.find('.stream-poster').css('background-image', 'url("' + safeText(stream.thumbUrl) + '")');
    $container.find('.session-user-avatar').css('background-image', 'url("' + safeText(stream.userAvatar) + '")').attr('title', user);
    $container.find('.session-user-name').text(user);
    $container.find('.player-badge').text(streamBadgeLabel(stream)).attr('title', streamProductName(stream));
    $container.find('.status i').attr('class', 'fa fa-' + safeText(stream.stateIcon)).attr('title', uCWord(stream.state));
    $container.find('.stream-title-cell').html(buildStreamTitleMarkup(stream));
    $container.find('.episode-meta').text(streamEpisodeMeta(stream));

    $container.find('.product-value').text(streamProductName(stream));
    $container.find('.quality-value').text(streamQualityValue(stream));
    $container.find('.stream-value').text(decisionLabel(stream.streamDecision));
    $container.find('.video-value').text(streamVideoValue(stream));
    $container.find('.audio-value').text(streamAudioValue(stream));
    $container.find('.location-value').text(location).attr('title', location);

    $container.find('.progressBar').css('width', percentPlayed + '%').attr('duration', safeText(stream.duration || 0));
    $container.attr('data-static-signature', streamStaticSignature(stream));
}

function updateFullStreamInfo(pollContext) {
    var context = safeText(pollContext || 'streams_page');
    plexStreamsPlusInitFocusTarget();
    return $.ajax('/plugins/plexstreamsplus/ajax.php').done(function(streams){
        if (streams.length > 0) {
            var currentDate = new Date();
            var lastUpdate = currentDate.getTime();
            var $streamHolder = $('#streams-container ul');
            if ($streamHolder.length === 0) {
                $('#no-streams, .no_streams').replaceWith('<div id="streams-container"><ul></ul></div>');
                $streamHolder = $('#streams-container ul');
            }
            if ($streamHolder.length === 0) {
                return;
            }
            streams.forEach(function(stream) {
                var sessionKey = streamSessionKey(stream);
                var legacyId = safeText(stream.id);
                var $matches = streamContainersByDataId($streamHolder, sessionKey);
                if ($matches.length === 0 && legacyId) {
                    $matches = streamContainersByDataId($streamHolder, legacyId);
                }
                if ($matches.length > 1) {
                    $matches.slice(1).each(function() {
                        $(this).removeAttr('data-psplus-live-time');
                        this.psplusLiveState = null;
                        $(this).remove();
                    });
                    $matches = $matches.first();
                }

                var $container = $matches.first();
                var node;
                if ($container.length > 0) {
                    var existingSignature = safeText($container.attr('data-static-signature'));
                    var latestSignature = streamStaticSignature(stream);
                    if (existingSignature !== latestSignature) {
                        refreshStreamCard($container, stream);
                    } else {
                        refreshStreamCardDynamic($container, stream);
                    }
                    node = $container[0];
                } else {
                    $container = $(buildFullStreamCard(stream)).appendTo($streamHolder);
                    $container.attr('data-static-signature', streamStaticSignature(stream));
                    node = $container[0];
                }
                $container.attr('id', streamDomId(sessionKey));
                $container.attr('data-stream-id', sessionKey);
                $container.attr('data-stream-legacy-id', legacyId);
                updateDuration(node, stream);
                $container.attr('updatedat', lastUpdate);
                node.prevState = stream.state;
                plexStreamsPlusTryFocusStream($container, sessionKey);
            });

            $streamHolder.children('.stream-container[updatedat]').each(function() {
                if ($(this).is('[updatedat]')) {
                    if ($(this).attr('updatedat') !== lastUpdate.toString()) {
                        $(this).removeAttr('data-psplus-live-time');
                        this.psplusLiveState = null;
                        $(this).remove();
                    }
                }
            });
        } else {
            if ($('#streams-container').length > 0) {
                $('#streams-container').replaceWith('<div class="no_streams"><p id="no-streams">' + _('There are currently no active streams') + '</p></div>');
            }
        }
        plexStreamsPlusMarkPoll(context, streams.length, false);
    }).fail(function(jqXHR) {
        plexStreamsPlusMarkPoll(context, 0, true);
        if (jqXHR.status == '500') {
            var setupMessage = '<div class="no_streams"><p id="no-streams">' + _('Please make sure you have') + ' <a href="/Settings/PlexStreamsPlus">' + _('setup') + '</a> ' + _('the plugin first') + '</p></div>';
            if ($('#streams-container').length > 0) {
                $('#streams-container').replaceWith(setupMessage);
            } else if ($('#no-streams').length > 0) {
                $('#no-streams').closest('.no_streams').replaceWith(setupMessage);
            }
        }
    });
}

function updateDuration(node, stream) {
    if (!node) {
        return;
    }
    var $container = $(node);

    if ($container.find('.currentPositionHours').length === 0 || $container.find('.currentPositionMinutes').length === 0 || $container.find('.currentPositionSeconds').length === 0) {
        var $position = $container.find('.position');
        if ($position.length > 0) {
            $position.html(streamPositionHtml(stream));
        }
    }

    plexStreamsPlusSyncLiveNode(node, stream);
}

function updateServerList(dest) {
    var list = [];
    $.each($("input[name='hostbox']:checked"), function(){
        list.push($(this).val());
    });
    $('#' + dest).val(list.join(','));
    var $selectedCount = $('#psplus-selected-count');
    if ($selectedCount.length > 0) {
        $selectedCount.text(list.length.toString());
    }
}

function plexStreamsPlusSetServerStatus(status, message) {
    var $status = $('#psplus-server-status');
    if ($status.length === 0) {
        return;
    }

    var classMap = ['is-info', 'is-success', 'is-warning', 'is-error', 'is-loading'];
    $status.removeClass(classMap.join(' '));

    var normalized = safeText(status).toLowerCase();
    if (classMap.indexOf('is-' + normalized) === -1) {
        normalized = 'info';
    }

    $status.addClass('is-' + normalized);
    if (message !== undefined) {
        $status.text(safeText(message));
    }
}

function plexStreamsPlusSetTokenState(state, noteOverride) {
    var $status = $('#psplus-token-status');
    var $note = $('#psplus-token-note');
    var $button = $('#psplus-get-token-btn');
    var $verifyButton = $('#psplus-verify-token-btn');
    var normalized = safeText(state).toLowerCase();
    var label = _('Not Connected');
    var note = _('Get a Plex token to discover available servers.');

    if (normalized === 'connected') {
        label = _('Connected');
        note = _('Token is stored locally and ready to use.');
    } else if (normalized === 'loading') {
        label = _('Authorizing');
        note = _('Complete Plex sign-in in the pop-up window.');
    } else if (normalized === 'error') {
        label = _('Token Error');
        note = _('Plex authorization failed. Try again.');
    } else {
        normalized = 'disconnected';
    }

    if ($status.length > 0) {
        $status.removeClass('is-connected is-disconnected is-loading is-error').addClass('is-' + normalized).text(label);
    }
    if ($note.length > 0) {
        $note.text(noteOverride !== undefined ? safeText(noteOverride) : note);
    }
    if ($button.length > 0) {
        $button.prop('disabled', normalized === 'loading');
    }
    if ($verifyButton.length > 0) {
        $verifyButton.prop('disabled', normalized === 'loading');
    }
}

function plexStreamsPlusUpdateSaveButtonState() {
    var $save = $('#psplus-save-btn');
    if ($save.length > 0) {
        $save.prop('disabled', !plexStreamsPlusSettingsState.customServersValid);
    }
}

function plexStreamsPlusSetTokenMasked(masked) {
    var $tokenInput = $('#plex-token');
    var $toggleButton = $('#psplus-toggle-token-btn');
    if ($tokenInput.length === 0) {
        return;
    }

    var shouldMask = masked !== false;
    $tokenInput.attr('type', shouldMask ? 'password' : 'text');
    if ($toggleButton.length > 0) {
        $toggleButton.text(shouldMask ? _('Show') : _('Hide'));
    }
}

function plexStreamsPlusToggleTokenMask() {
    var $tokenInput = $('#plex-token');
    if ($tokenInput.length === 0) {
        return;
    }
    var isCurrentlyMasked = $tokenInput.attr('type') !== 'text';
    plexStreamsPlusSetTokenMasked(!isCurrentlyMasked);
}

function plexStreamsPlusParseCustomServerEntries(rawValue) {
    var raw = safeText(rawValue).trim();
    if (raw.length === 0) {
        return {
            entries: [],
            invalidEntries: []
        };
    }

    var entries = raw.split(/[,\n]+/).map(function(entry) {
        return safeText(entry).trim();
    }).filter(function(entry) {
        return entry.length > 0;
    });

    var endpointPattern = /^(https?:\/\/)?(([A-Za-z0-9._-]+)|(\[[0-9a-fA-F:]+\])|(\d{1,3}(?:\.\d{1,3}){3}))(?::\d{1,5})?$/;
    var invalidEntries = [];
    entries.forEach(function(entry) {
        if (!endpointPattern.test(entry)) {
            invalidEntries.push(entry);
        }
    });

    return {
        entries: entries,
        invalidEntries: invalidEntries
    };
}

function plexStreamsPlusValidateCustomServers() {
    var $input = $('#CUSTOM_SERVERS');
    if ($input.length === 0) {
        return true;
    }
    var $feedback = $('#psplus-custom-servers-feedback');
    var result = plexStreamsPlusParseCustomServerEntries($input.val());

    if (result.entries.length === 0) {
        $input.removeClass('psplus-input-invalid');
        if ($feedback.length > 0) {
            $feedback.removeClass('is-error is-success').addClass('is-info').text(_('Optional: add comma-separated host:port endpoints.'));
        }
        plexStreamsPlusSettingsState.customServersValid = true;
        plexStreamsPlusUpdateSaveButtonState();
        return true;
    }

    if (result.invalidEntries.length > 0) {
        $input.addClass('psplus-input-invalid');
        if ($feedback.length > 0) {
            var preview = result.invalidEntries.slice(0, 2).join(', ');
            if (result.invalidEntries.length > 2) {
                preview += ', ...';
            }
            $feedback.removeClass('is-info is-success').addClass('is-error').text(_('Invalid endpoint format: ') + preview);
        }
        plexStreamsPlusSettingsState.customServersValid = false;
        plexStreamsPlusUpdateSaveButtonState();
        return false;
    }

    $input.removeClass('psplus-input-invalid');
    if ($feedback.length > 0) {
        $feedback.removeClass('is-info is-error').addClass('is-success').text(_('Custom server endpoints look valid.'));
    }
    plexStreamsPlusSettingsState.customServersValid = true;
    plexStreamsPlusUpdateSaveButtonState();
    return true;
}

function plexStreamsPlusStartOAuth() {
    if (typeof PlexOAuth !== 'function') {
        return;
    }

    plexStreamsPlusSetTokenState('loading');
    PlexOAuth(
        function(token) {
            $('#plex-token').val(token);
            plexStreamsPlusSetTokenMasked(true);
            plexStreamsPlusSetTokenState('connected', _('Token received. Use Verify Token to confirm access before saving.'));
            getServers('#hostcontainer', $('#HOST').val());
        },
        function() {
            var existingToken = safeText($('#plex-token').val()).trim();
            if (existingToken.length > 0) {
                plexStreamsPlusSetTokenState('connected', _('Authorization cancelled. Existing token kept.'));
            } else {
                plexStreamsPlusSetTokenState('error');
            }
        },
        function() {
            plexStreamsPlusSetTokenState('loading');
        }
    );
}

function plexStreamsPlusVerifyToken() {
    var token = safeText($('#plex-token').val()).trim();
    var useSsl = $('input[name="FORCE_PLEX_HTTPS"]:checked').val();
    var $verifyButton = $('#psplus-verify-token-btn');
    if (token.length === 0) {
        plexStreamsPlusSetTokenState('error', _('No token to verify.'));
        return;
    }

    $verifyButton.prop('disabled', true);
    plexStreamsPlusSetTokenState('loading', _('Verifying token with Plex...'));
    $.ajax({
        url: '/plugins/plexstreamsplus/getServers.php?useSsl=' + useSsl,
        method: 'GET',
        dataType: 'json',
        timeout: 15000
    }).done(function(data) {
        var serverList = data && data.serverList ? data.serverList : {};
        var count = Object.keys(serverList).length;
        if (count > 0) {
            plexStreamsPlusSetTokenState('connected', _('Token verified. ') + count + ' ' + _('server(s) available.'));
        } else {
            plexStreamsPlusSetTokenState('connected', _('Token verified. No servers returned.'));
        }
        getServers('#hostcontainer', $('#HOST').val());
    }).fail(function(jqXHR, textStatus) {
        if (jqXHR && jqXHR.status === 500) {
            plexStreamsPlusSetTokenState('error', _('Token verification failed. Please generate a new token.'));
            return;
        }
        if (textStatus === 'timeout') {
            plexStreamsPlusSetTokenState('error', _('Token verification timed out. Please try again.'));
            return;
        }
        plexStreamsPlusSetTokenState('error', _('Unable to verify token right now.'));
    }).always(function() {
        $verifyButton.prop('disabled', false);
    });
}

function plexStreamsPlusInitSettingsPage() {
    var $form = $('#plexstreamsplus_settings');
    if ($form.length === 0) {
        return;
    }
    if ($form.data('psplusUxInit')) {
        return;
    }
    $form.data('psplusUxInit', true);

    $('#psplus-refresh-servers').on('click', function() {
        getServers('#hostcontainer', $('#HOST').val());
    });

    $('#psplus-verify-token-btn').on('click', function() {
        plexStreamsPlusVerifyToken();
    });

    $('#psplus-toggle-token-btn').on('click', function() {
        plexStreamsPlusToggleTokenMask();
    });

    $('#CUSTOM_SERVERS').on('input blur', function() {
        plexStreamsPlusValidateCustomServers();
    });

    $('#plex-token').on('input change', function() {
        var hasToken = safeText($(this).val()).trim().length > 0;
        plexStreamsPlusSetTokenState(hasToken ? 'connected' : 'disconnected', hasToken ? _('Token updated. Verify before saving.') : undefined);
        plexStreamsPlusSetTokenMasked(true);
    });

    plexStreamsPlusValidateCustomServers();
    plexStreamsPlusSetTokenMasked(true);
    plexStreamsPlusSetTokenState(safeText($('#plex-token').val()).trim().length > 0 ? 'connected' : 'disconnected');
    plexStreamsPlusSetServerStatus('info', _('Ready to load Plex servers.'));
    plexStreamsPlusUpdateSaveButtonState();
}

function getServers(containerSelector, selected) {
    var url = '/plugins/plexstreamsplus/getServers.php?useSsl=' + $('input[name="FORCE_PLEX_HTTPS"]:checked').val();
    var $host = $(containerSelector);
    var $spinner = $host.closest('.psplus-card').find('.lds-dual-ring');
    if ($spinner.length === 0) {
        $spinner = $('.psplus-server-load .lds-dual-ring');
    }
    var selectedRaw = safeText(selected);
    var selectedList = selectedRaw.split(',').map(function(item) {
        return safeText(item).trim();
    }).filter(function(item) {
        return item.length > 0;
    });
    var token = safeText($('#plex-token').val()).trim();

    $host.hide();
    $spinner.show();
    $host.html('');
    plexStreamsPlusSetServerStatus('loading', _('Checking Plex servers...'));

    if (token.length === 0) {
        $host.html('<p class="psplus-server-empty">' + _('No Plex token found. Click Get Plex Token to load servers.') + '</p>');
        updateServerList('HOST');
        $host.show();
        $spinner.hide();
        plexStreamsPlusSetServerStatus('warning', _('No token configured.'));
        return $.Deferred().resolve().promise();
    }

    return $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        timeout: 15000
    }).done(function(data) {
        plexStreamsPlusServerList = data && data.serverList ? data.serverList : {};
        var endpointCount = 0;
        var serverCount = 0;

        if (Object.keys(plexStreamsPlusServerList).length > 0) {
            for (var id in plexStreamsPlusServerList) {
                if (plexStreamsPlusServerList.hasOwnProperty(id)) {
                    serverCount += 1;
                    var server = plexStreamsPlusServerList[id];
                    plexStreamsPlusServerList[id].Connections.forEach(function(connection, connectionIndex) {
                        if (connection !== null) {
                            endpointCount += 1;
                            var shortHost = connection.uri;
                            shortHost = shortHost.replace(connection.protocol  + '://', '');
                            if (connection.port) {
                                shortHost = shortHost.replace(':' + connection.port, '');
                            }
                            var aliasKey = shortHost.replace(/[^A-Za-z0-9_-]/g, '_');
                            var safeServerName = plexStreamsPlusEscapeHtml(server.Name);
                            var safeUri = plexStreamsPlusEscapeHtml(connection.uri);
                            var safeAddress = plexStreamsPlusEscapeHtml(connection.address);
                            var safePort = plexStreamsPlusEscapeHtml(connection.port);
                            var checkboxId = 'hostbox-' + aliasKey + '-' + safeText(id).replace(/[^A-Za-z0-9_-]/g, '_') + '-' + connectionIndex;
                            var isRemote = safeText(connection.local) === '0';
                            var scopeLabel = isRemote ? _('Remote') : _('Local');
                            var scopeClass = isRemote ? 'is-remote' : 'is-local';
                            $host.append('<input type="hidden" name="ALIAS-' + aliasKey + '" value="' + safeServerName + '"/>');
                            $host.append(
                                '<div class="psplus-server-item">' +
                                    '<input type="checkbox" onchange="updateServerList(\'HOST\')" name="hostbox" id="' + checkboxId + '" data-id="' + plexStreamsPlusEscapeHtml(id) + '"' + (selectedList.indexOf(connection.uri) > -1 ? ' checked="checked"' : '' ) + ' value="' + safeUri + '" data-address="' + safeAddress + '" data-name="' + safeServerName + '"/>' +
                                    '<label class="psplus-server-label" for="' + checkboxId + '">' +
                                        '<span class="psplus-server-main">' +
                                            '<span class="psplus-server-name">' + safeServerName + '</span>' +
                                            '<span class="psplus-server-address">' + safeAddress + ':' + safePort + '</span>' +
                                        '</span>' +
                                        '<span class="psplus-server-scope ' + scopeClass + '">' + plexStreamsPlusEscapeHtml(scopeLabel) + '</span>' +
                                    '</label>' +
                                '</div>'
                            );
                        }
                    });
                }
            }
            plexStreamsPlusSetServerStatus('success', _('Found') + ' ' + endpointCount + ' ' + _('endpoint(s) across') + ' ' + serverCount + ' ' + _('server(s).'));
        } else {
            $host.html('<p class="psplus-server-empty">' + _('No servers found. Add one under Custom Servers.') + '</p>');
            plexStreamsPlusSetServerStatus('warning', _('No servers returned by Plex discovery.'));
        }
        updateServerList('HOST');
        $host.show();
        $spinner.hide();
    }).fail(function(jqXHR, textStatus) {
        var reason = textStatus === 'timeout' ? _('Server discovery request timed out.') : _('Unable to reach Plex discovery right now.');
        $host.html('<p class="psplus-server-empty">' + reason + ' ' + _('Use Refresh to try again.') + '</p>');
        updateServerList('HOST');
        $host.show();
        $spinner.hide();
        plexStreamsPlusSetServerStatus('error', reason);
    });
}

function plexStreamsPlusSetLocalStorage(key, value, path) {
    if (path !== false) {
        key = key + '_' + window.location.pathname;
    }
    localStorage.setItem(key, value);
}
function plexStreamsPlusGetLocalStorage(key, default_value, path) {
    if (path !== false) {
        key = key + '_' + window.location.pathname;
    }
    var value = localStorage.getItem(key);
    if (value !== null) {
        return value
    } else if (default_value !== undefined) {
        plexStreamsPlusSetLocalStorage(key, default_value, path);
        return default_value
    }
}

function PopupCenter(url, title, w, h) {
    // Fixes dual-screen position                         Most browsers      Firefox
    var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : window.screenX;
    var dualScreenTop = window.screenTop != undefined ? window.screenTop : window.screenY;

    var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
    var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

    var left = ((width / 2) - (w / 2)) + dualScreenLeft;
    var top = ((height / 2) - (h / 2)) + dualScreenTop;
    var newWindow = window.open(url, title, 'scrollbars=yes, width=' + w + ', height=' + h + ', top=' + top + ', left=' + left);

    // Puts focus on the newWindow
    if (window.focus) {
        newWindow.focus();
    }

    return newWindow;
}
var plex_oauth_loader = '<style>' +
        '.login-loader-container {' +
            'font-family: "Open Sans", Arial, sans-serif;' +
            'position: absolute;' +
            'top: 0;' +
            'right: 0;' +
            'bottom: 0;' +
            'left: 0;' +
        '}' +
        '.login-loader-message {' +
            'color: #282A2D;' +
            'text-align: center;' +
            'position: absolute;' +
            'left: 50%;' +
            'top: 25%;' +
            'transform: translate(-50%, -50%);' +
        '}' +
        '.login-loader {' +
            'border: 5px solid #ccc;' +
            '-webkit-animation: spin 1s linear infinite;' +
            'animation: spin 1s linear infinite;' +
            'border-top: 5px solid #282A2D;' +
            'border-radius: 50%;' +
            'width: 50px;' +
            'height: 50px;' +
            'position: relative;' +
            'left: calc(50% - 25px);' +
        '}' +
        '@keyframes spin {' +
            '0% { transform: rotate(0deg); }' +
            '100% { transform: rotate(360deg); }' +
        '}' +
    '</style>' +
    '<div class="login-loader-container">' +
        '<div class="login-loader-message">' +
            '<div class="login-loader"></div>' +
            '<br>' +
            'Redirecting to the Plex login page...' +
        '</div>' +
    '</div>';
var plex_oauth_window = null;
function closePlexOAuthWindow() {
    if (plex_oauth_window) {
        plex_oauth_window.close();
    }
}

function uuidv4() {
    return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, function(c) {
        var cryptoObj = window.crypto || window.msCrypto; // for IE 11
        return (c ^ cryptoObj.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
    });
}

function getPlexHeaders() {
    return {
        'Accept': 'application/json',
        'X-Plex-Product': 'Unraid PlexStreams Plus Plugin',
        'X-Plex-Version': PLUGIN_VERSION,
        'X-Plex-Client-Identifier': plexStreamsPlusGetLocalStorage('UnraidPlexStreamsPlus_ClientID', uuidv4(), false),
        'X-Plex-Platform': 'unraid',
        'X-Plex-Platform-Version': OS_VERSION,
        'X-Plex-Model': 'Plex OAuth',
        'X-Plex-Device': OS_VERSION,
        'X-Plex-Device-Name': 'Unraid PlexStreams Plus Plugin',
        'X-Plex-Device-Screen-Resolution': window.screen.width + 'x' + window.screen.height,
        'X-Plex-Language': 'en'
    };
}

var plexStreamsPlusGetPlexOAuthPin = function () {
    var x_plex_headers = getPlexHeaders();
    var deferred = $.Deferred();

    $.ajax({
        url: 'https://plex.tv/api/v2/pins?strong=true',
        type: 'POST',
        headers: x_plex_headers,
        success: function(data) {
            deferred.resolve({pin: data.id, code: data.code});
        },
        error: function() {
            closePlexOAuthWindow();
            deferred.reject();
        }
    });
    return deferred;
};

var polling = null;

function encodeData(data) {
    return Object.keys(data).map(function(key) {
        return [key, data[key]].map(encodeURIComponent).join("=");
    }).join("&");
}

function PlexOAuth(success, error, pre) {
    if (typeof pre === "function") {
        pre()
    }
    closePlexOAuthWindow();
    plex_oauth_window = PopupCenter('', 'Plex-OAuth', 600, 700);
    $(plex_oauth_window.document.body).html(plex_oauth_loader);

    plexStreamsPlusGetPlexOAuthPin().then(function (data) {
        var x_plex_headers = getPlexHeaders();
        const pin = data.pin;
        const code = data.code;

        var oauth_params = {
            'clientID': x_plex_headers['X-Plex-Client-Identifier'],
            'context[device][product]': x_plex_headers['X-Plex-Product'],
            'context[device][version]': x_plex_headers['X-Plex-Version'],
            'context[device][platform]': x_plex_headers['X-Plex-Platform'],
            'context[device][platformVersion]': x_plex_headers['X-Plex-Platform-Version'],
            'context[device][device]': x_plex_headers['X-Plex-Device'],
            'context[device][deviceName]': x_plex_headers['X-Plex-Device-Name'],
            'context[device][model]': x_plex_headers['X-Plex-Model'],
            'context[device][screenResolution]': x_plex_headers['X-Plex-Device-Screen-Resolution'],
            'context[device][layout]': 'desktop',
            'code': code
        }

        plex_oauth_window.location = 'https://app.plex.tv/auth/#!?' + encodeData(oauth_params);
        polling = pin;

        (function poll() {
            $.ajax({
                url: 'https://plex.tv/api/v2/pins/' + pin,
                type: 'GET',
                headers: x_plex_headers,
                success: function (data) {
                    if (data.authToken){
                        closePlexOAuthWindow();
                        getServers('#hostcontainer', $('#HOST').val());
                        if (typeof success === "function") {
                            success(data.authToken)
                        }
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    if (textStatus !== "timeout") {
                        closePlexOAuthWindow();
                        if (typeof error === "function") {
                            error()
                        }
                    }
                },
                complete: function () {
                    if (!plex_oauth_window.closed && polling === pin){
                        setTimeout(function() {poll()}, 1000);
                    }
                },
                timeout: 10000
            });
        })();
    }, function () {
        closePlexOAuthWindow();
        if (typeof error === "function") {
            error()
        }
    });
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        safeText: safeText,
        decisionLabel: decisionLabel,
        streamAttr: streamAttr,
        streamVideoValue: streamVideoValue,
        streamAudioValue: streamAudioValue,
        streamEpisodeMeta: streamEpisodeMeta,
        streamDomId: streamDomId,
        streamStaticSignature: streamStaticSignature,
        plexStreamsPlusParseCustomServerEntries: plexStreamsPlusParseCustomServerEntries,
        plexStreamsPlusResetPollState: plexStreamsPlusResetPollState,
        plexStreamsPlusMarkPoll: plexStreamsPlusMarkPoll,
        plexStreamsPlusNextPollDelay: plexStreamsPlusNextPollDelay,
        buildFullStreamCard: buildFullStreamCard
    };
}

