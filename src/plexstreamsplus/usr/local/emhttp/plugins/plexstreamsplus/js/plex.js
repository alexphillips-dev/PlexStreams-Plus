var plexStreamsPlusServerList = [];

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

function streamTimeHtml(stream, includeEndTime) {
    if (stream.currentPositionHours === null || stream.currentPositionHours === undefined) {
        return 'N/A';
    }
    var endTime = includeEndTime ? ' (<span class="endTime">' + plexStreamsPlusEscapeHtml(stream.endTime) + '</span>)' : '';
    return '<span class="currentPositionHours">' + stream.currentPositionHours.toString().padStart(2, 0) + '</span>:' +
        '<span class="currentPositionMinutes">' + stream.currentPositionMinutes.toString().padStart(2, 0) + '</span>:' +
        '<span class="currentPositionSeconds">' + stream.currentPositionSeconds.toString().padStart(2, 0) + '</span> / ' +
        plexStreamsPlusEscapeHtml(stream.lengthDisplay) + endTime;
}


function updateDashboardStreamsNew() {
    $.ajax('/plugins/plexstreamsplus/ajax.php').done(function(streams){
        $('#plexstreamsplus_count').html(streams.length);
        $('#retrieving_streams').remove();
        var hostStreams = [];
        if (streams.length > 0) {
            $('.no_streams').remove();
            var currentDate = new Date();
            var lastUpdate = currentDate.getTime();
            var hostStreams = [];
            streams.forEach(function(stream) {
                var $container = $('#' + stream.id);
                var hostName = streamServerName(stream);
                if (hostStreams[hostName] === undefined) {
                    hostStreams[hostName] = 1;
                } else {
                    hostStreams[hostName] = hostStreams[hostName] + 1;
                }
                if ($container.length === 0) {
                    $container = $('<div id="' + plexStreamsPlusEscapeHtml(stream.id) + '">' +
                        '<span class="w36"><p class="plexstream-title" title="' + plexStreamsPlusEscapeHtml(stream.titleString) + '">' + plexStreamsPlusEscapeHtml(stream.title) +  '</p></span>' +
                        '<span class="w18" style="text-align:center;"><i class="fa fa-' + plexStreamsPlusEscapeHtml(stream.stateIcon) + '" title="' + plexStreamsPlusEscapeHtml(stream.state) + '"></i></span>' +
                        '<span class="w18" style="text-align:center;"><p class="plexstream-user" title="' + plexStreamsPlusEscapeHtml(stream.user) + '">' + plexStreamsPlusEscapeHtml(stream.user) + '</p></span>' +
                        '<span class="w18" style="text-align:right;"><p class="plexstream-time">' + streamTimeHtml(stream, true) + '</p></span>' +
                    '</div>').appendTo('#plexstreamsplus_streams');
                    var node = $container[0];
                } else {
                    var node = $container[0];
                    var $cells = $container.find('span');
                    $($cells[1]).find('i').attr('class', 'fa fa-' + stream.stateIcon).attr('title', uCWord(stream.state));
                }
                updateDuration(node, stream);
                $container.attr('updatedat', lastUpdate);
                node.prevState = stream.state;
            });
            $('#stream_count_container').html('');
            for (var host in hostStreams) {
                if(hostStreams.hasOwnProperty(host)) {
                    $('#stream_count_container').append('<div><strong>' + plexStreamsPlusEscapeHtml(host) + ':</strong> ' + hostStreams[host] + ' ' +  _('Active Stream(s)') + '</div>');
                }
            }
            $('#plexstreamsplus_streams [updatedat]').each(function() {
                if ($(this).is('[updatedat]')) {
                    if ($(this).attr('updatedat') !== lastUpdate.toString()) {
                        if (this.timer) {
                            clearInterval(this.timer)
                        };
                        $(this).remove();
                    }
                }
            });
        } else {
            $('#stream_count_container').html('<span id="plexstreamsplus_count">0</span> ' + _('Active Stream(s)') + '</span>');
            $('#plexstreamsplus_streams').html('<div class="no_streams"><span class="w100"><p style="text-align:center;font-style:italic;font-size:13px;">' + _('There are currently no active streams') + '</p></span></div>');
        }
    }).fail(function(jqXHR) {
        if (jqXHR.status == '500') {
            $('#plexstreamsplus_streams').html('<span class="w100"><p style="text-align:center;font-style:italic;font-size:13px;">' + _('Please make sure you have') + ' <a href="/Settings/PlexStreamsPlus">' + _('setup') + '</a> ' + _('the plugin first') + '</p></span>');
        }
    });
}


function updateDashboardStreams() {
    $.ajax('/plugins/plexstreamsplus/ajax.php').done(function(streams){
        //$('#plexstreamsplus_count').html(streams.length);
        $('#retrieving_streams').remove();
        if (streams.length > 0) {
            $('.no_streams').remove();
            var currentDate = new Date();
            var lastUpdate = currentDate.getTime();
            var hostStreams = [];
            streams.forEach(function(stream) {
                var $container = $('#' + stream.id);
                var hostName = streamServerName(stream);
                if (hostStreams[hostName] === undefined) {
                    hostStreams[hostName] = 1;
                } else {
                    hostStreams[hostName] = hostStreams[hostName] + 1;
                }
                if ($container.length === 0) {
                    $container = $('<tr style="display:table-row;" id="' + plexStreamsPlusEscapeHtml(stream.id) + '">' +
                        '<td width="40%" style="padding: 0px;"><p class="plexstream-title" title="' + plexStreamsPlusEscapeHtml(stream.titleString) + '">' + plexStreamsPlusEscapeHtml(stream.title) +  '</p></td>' +
                        '<td align="center" style="padding: 0px;text-align:center;"><i class="fa fa-' + plexStreamsPlusEscapeHtml(stream.stateIcon) + '" title="' + plexStreamsPlusEscapeHtml(stream.state) + '"></i></td>' +
                        '<td align="center" style="padding: 0px;"><p class="plexstream-user" title="' + plexStreamsPlusEscapeHtml(stream.user) + '">' + plexStreamsPlusEscapeHtml(stream.user) + '</td>' +
                        '<td align="center" style="padding: 0px;text-align:right;"><p class="plexstream-time">' + streamTimeHtml(stream, false) + '</p></td>' +
                    '</tr>').appendTo('#plexstreamsplus_streams');
                    var node = $container[0];
                } else {
                    var node = $container[0];
                    var $cells = $container.find('td');
                    $($cells[1]).find('i').attr('class', 'fa fa-' + stream.stateIcon).attr('title', uCWord(stream.state));
                }
                $container.attr('updatedat', lastUpdate);
                node.prevState = stream.state;
                updateDuration(node, stream);
            });
            $('#stream_count_container').html('');
            for (var host in hostStreams) {
                if(hostStreams.hasOwnProperty(host)) {
                    $('#stream_count_container').append('<div><strong>' + plexStreamsPlusEscapeHtml(host) + ':</strong> ' + hostStreams[host] + ' ' +  _('Active Stream(s)') + '</div>');
                }
            }
            $('#plexstreamsplus_streams tr[updatedat]').each(function() {
                if ($(this).is('[updatedat]')) {
                    if ($(this).attr('updatedat') !== lastUpdate.toString()) {
                        if (this.timer) {
                            clearInterval(this.timer)
                        };
                        $(this).remove();
                    }
                }
            });
        } else {
            $('#stream_count_container').html('<span id="plexstreamsplus_count">0</span> ' + _('Active Stream(s)') + '</span>');
            $('#plexstreamsplus_streams').html('<tr class="no_streams"><td colspan="4" align="center" style="padding: 0 0 0 0;"><p style="text-align:center;font-style:italic;">' + _('There are currently no active streams') + '</p></td></tr>');
        }
    }).fail(function(jqXHR) {
        if (jqXHR.status == '500') {
            $('#plexstreamsplus_streams').html('<tr><td colspan="4" align="center"><p style="text-align:center;font-style:italic;">' + _('Please make sure you have') + ' <a href="/Settings/PlexStreamsPlus">' + _('setup') + '</a> ' + _('the plugin first') + '</p></td></tr>');
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
        return safeText($(this).attr('data-stream-id')) === expectedId;
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
        return 'S' + seasonMatch[1] + ' · ' + seasonMatch[2].trim();
    }

    var episodeMatch = title.match(/S(\d+)\s*E(\d+)/i);
    if (episodeMatch) {
        return 'S' + episodeMatch[1] + ' · E' + episodeMatch[2];
    }

    var movieMatch = title.match(/\((20\d{2}|19\d{2})\)\s*$/);
    if (movieMatch) {
        return 'Movie · ' + movieMatch[1];
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
    var safeId = plexStreamsPlusEscapeHtml(streamDomId(streamIdRaw));
    var safeStreamId = plexStreamsPlusEscapeHtml(streamIdRaw);
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

    return '<li class="stream-container" id="' + safeId + '" data-stream-id="' + safeStreamId + '" data-stream-type="' + safeType + '">' +
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
}

function updateFullStreamInfo() {
    $.ajax('/plugins/plexstreamsplus/ajax.php').done(function(streams){
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
                var $matches = streamContainersByDataId($streamHolder, stream.id);
                if ($matches.length > 1) {
                    $matches.slice(1).each(function() {
                        if (this.timer) {
                            clearInterval(this.timer);
                        }
                        $(this).remove();
                    });
                    $matches = $matches.first();
                }

                var $container = $matches.first();
                var node;
                if ($container.length > 0) {
                    refreshStreamCard($container, stream);
                    node = $container[0];
                } else {
                    $container = $(buildFullStreamCard(stream)).appendTo($streamHolder);
                    node = $container[0];
                }
                updateDuration(node, stream);
                $container.attr('updatedat', lastUpdate);
                node.prevState = stream.state;
            });

            $streamHolder.children('.stream-container[updatedat]').each(function() {
                if ($(this).is('[updatedat]')) {
                    if ($(this).attr('updatedat') !== lastUpdate.toString()) {
                        if (this.timer) {
                            clearInterval(this.timer)
                        };
                        $(this).remove();
                    }
                }
            });
        } else {
            if ($('#streams-container').length > 0) {
                $('#streams-container').replaceWith('<div class="no_streams"><p id="no-streams">' + _('There are currently no active streams') + '</p></div>');
            }
        }
    }).fail(function(jqXHR) {
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
    var $container = $(node);

    if (!stream.duration) {
        if (node.timer) {
            clearInterval(node.timer);
            node.timer = undefined;
        }
        $container.find('.position').html(streamPositionHtml(stream));
        if (stream.endTime) {
            $container.find('.endTime').text(stream.endTime);
        }
        return;
    }

    var $hours = $container.find('.currentPositionHours');
    var $minutes = $container.find('.currentPositionMinutes');
    var $seconds = $container.find('.currentPositionSeconds');

    if (!$hours.length || !$minutes.length || !$seconds.length) {
        $container.find('.position').html(streamPositionHtml(stream));
        $hours = $container.find('.currentPositionHours');
        $minutes = $container.find('.currentPositionMinutes');
        $seconds = $container.find('.currentPositionSeconds');
    }

    if (node.prevState && node.prevState !== stream.state) {
        $hours.html(stream.currentPositionHours.toString().padStart(2, 0));
        $minutes.html(stream.currentPositionMinutes.toString().padStart(2, 0));
        $seconds.html(stream.currentPositionSeconds.toString().padStart(2, 0));
        if (stream.state === 'playing') {
            incrementTimer($hours, $minutes, $seconds);
        }
    }
    if (stream.state === 'playing' && !node.timer) {
        node.timer = setInterval(incrementTimer, 1000, $hours, $minutes, $seconds);
    } else if(stream.state !== 'playing') {
        if (node.timer) {
            clearInterval(node.timer);
            node.timer = undefined;
        }
        $hours.html(stream.currentPositionHours.toString().padStart(2, 0));
        $minutes.html(stream.currentPositionMinutes.toString().padStart(2, 0));
        $seconds.html(stream.currentPositionSeconds.toString().padStart(2, 0));
    }
    if (stream.endTime) {
        $container.find('.endTime').text(stream.endTime);
    }
}

function incrementTimer($hours, $minutes, $seconds) {
    var seconds = parseInt($seconds.html(), 10);
    var minutes = parseInt($minutes.html(), 10);
    var hours = parseInt($hours.html());
    seconds += 1;
    if (seconds > 59) {
        seconds = 0;
        minutes += 1;
    }
    if (minutes > 59) {
        minutes = 0;
        hours += 1;
    }
    $seconds.html(seconds.toString().padStart(2, 0));
    $minutes.html(minutes.toString().padStart(2, 0));
    $hours.html(hours.toString().padStart(2, 0));
}

function updateServerList(dest) {
    var list = [];
    $.each($("input[name='hostbox']:checked"), function(){
        list.push($(this).val());
    });
    $('#' + dest).val(list.join(','));
}

function getServers(containerSelector, selected) {
    var url = '/plugins/plexstreamsplus/getServers.php?useSsl=' + $('input[name="FORCE_PLEX_HTTPS"]:checked').val();
    var $host = $(containerSelector);
    $host.hide();
    $('.lds-dual-ring').show();
    selected = selected.split(',');
    $host.html('');
    $.get(url).done(function(data) {
        plexStreamsPlusServerList = data.serverList;
        if (Object.keys(plexStreamsPlusServerList).length > 0) {
            for (var id in plexStreamsPlusServerList) {
                if (plexStreamsPlusServerList.hasOwnProperty(id)) {
                    var server = plexStreamsPlusServerList[id];
                    plexStreamsPlusServerList[id].Connections.forEach(function(connection) {
                        if (connection !== null) {
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
                            $host.append('<input type="hidden" name="ALIAS-' + aliasKey + '" value="' + safeServerName + '"/>');
                            $host.append('<input type="checkbox" onchange="updateServerList(\'HOST\')" name="hostbox" id="' + safeUri + '" data-id="' + plexStreamsPlusEscapeHtml(id) + '"' + (selected.indexOf(connection.uri) > -1 ? ' checked="checked"' : '' ) + ' value="' + safeUri + '" data-address="' + safeAddress + '" data-name="' + safeServerName + '"/> <label for="' + safeUri + '"> ' + safeServerName + ' (' +  safeAddress + ':' + safePort + ')' + (connection.local === '0' ? ' - Remote' : '') + '</label><br/>');
                        }
                    });
                }
            }
        } else {
            $host.html('<p>No Servers found, please enter server in Custom Servers Field');
        }
        $host.show();
        $('.lds-dual-ring').hide();
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

