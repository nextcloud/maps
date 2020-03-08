import { generateUrl } from '@nextcloud/router';

import { dirname, brify, metersToDistance, metersToElevation, kmphToSpeed, minPerKmToPace, formatTimeSeconds, getUrlParameter } from './utils';

function TracksController(optionsController, timeFilterController) {
    this.track_MARKER_VIEW_SIZE = 30;
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;

    this.mainLayer = null;
    this.elevationControl = null;
    this.closeElevationButton = null;
    // indexed by track id
    // those actually added to map, those which get toggled
    this.mapTrackLayers = {};
    // layers which actually contain lines/waypoints, those which get filtered
    this.trackLayers = {};
    this.trackColors = {};
    this.trackDivIcon = {};
    this.tracks = {};

    this.firstDate = null;
    this.lastDate = null;

    // used by optionsController to know if tracks loading
    // was done before or after option restoration
    this.trackListLoaded = false;

    this.changingColorOf = null;
    this.lastZIndex = 1000;
    this.sortOrder = 'name';
}

TracksController.prototype = {

    // set up favorites-related UI stuff
    initController : function(map) {
        this.map = map;
        this.mainLayer = L.featureGroup();
        this.mainLayer.on('click', this.getTrackMarkerOnClickFunction());
        var that = this;
        // UI events
        // toggle a track
        $('body').on('click', '.track-line .track-name', function(e) {
            var id = $(this).parent().attr('track');
            that.toggleTrack(id, true);
        });
        // zoom on track
        $('body').on('click', '.zoomTrackButton', function(e) {
            var id = $(this).parent().parent().parent().parent().attr('track');
            that.zoomOnTrack(id);
        });
        // sort
        $('body').on('click', '#sort-name-tracks', function(e) {
            that.sortOrder = 'name';
            that.sortTracks();
            that.optionsController.saveOptionValues({tracksSortOrder: 'name'});
        });
        $('body').on('click', '#sort-date-tracks', function(e) {
            that.sortOrder = 'date';
            that.sortTracks();
            that.optionsController.saveOptionValues({tracksSortOrder: 'date'});
        });
        // show/hide all tracks
        $('body').on('click', '#select-all-tracks', function(e) {
            that.showAllTracks();
            var trackList = Object.keys(that.trackLayers);
            var trackStringList = trackList.join('|');
            that.optionsController.saveOptionValues({enabledTracks: trackStringList});
            that.optionsController.enabledTracks = trackList;
            that.optionsController.saveOptionValues({tracksEnabled: that.map.hasLayer(that.mainLayer)});
        });
        $('body').on('click', '#select-no-tracks', function(e) {
            that.hideAllTracks();
            var trackStringList = '';
            that.optionsController.saveOptionValues({enabledTracks: trackStringList});
            that.optionsController.enabledTracks = [];
            that.optionsController.saveOptionValues({tracksEnabled: that.map.hasLayer(that.mainLayer)});
        });
        // toggle tracks
        $('body').on('click', '#navigation-tracks > a', function(e) {
            that.toggleTracks();
            that.optionsController.saveOptionValues({tracksEnabled: that.map.hasLayer(that.mainLayer)});
            that.updateMyFirstLastDates(true);
            if (that.map.hasLayer(that.mainLayer) && !$('#navigation-tracks').hasClass('open')) {
                that.toggleTrackList();
                that.optionsController.saveOptionValues({trackListShow: $('#navigation-tracks').hasClass('open')});
            }
        });
        // expand track list
        $('body').on('click', '#navigation-tracks', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-tracks') {
                that.toggleTrackList();
                that.optionsController.saveOptionValues({trackListShow: $('#navigation-tracks').hasClass('open')});
            }
        });
        $('body').on('click', '.changeTrackColor', function(e) {
            var id = $(this).parent().parent().parent().parent().attr('track');
            that.askChangeTrackColor(id);
        });
        // context menu event
        $('body').on('click', '.contextChangeTrackColor', function(e) {
            var id = parseInt($(this).parent().parent().attr('trackid'));
            that.askChangeTrackColor(id);
            that.map.closePopup();
        });
        $('body').on('change', '#trackcolorinput', function(e) {
            that.okColor();
        });
        $('body').on('click', '.drawElevationButton', function(e) {
            var id = $(this).attr('track');
            that.showTrackElevation(id);
        });
        $('body').on('click', '.contextShowElevation', function(e) {
            var id = parseInt($(this).parent().parent().attr('trackid'));
            that.showTrackElevation(id);
            that.map.closePopup();
        });
        $('body').on('click', '.showTrackElevation', function(e) {
            var id = $(this).parent().parent().parent().parent().attr('track');
            that.showTrackElevation(id);
        });
        // close elevation char button
        this.closeElevationButton = L.easyButton({
            position: 'bottomleft',
            states: [{
                stateName: 'no-importa',
                icon:      'fa-times',
                title:     t('maps', 'Close elevation chart'),
                onClick: function(btn, map) {
                    that.clearElevationControl();
                }
            }]
        });
    },

    // expand or fold track list in sidebar
    toggleTrackList: function() {
        $('#navigation-tracks').toggleClass('open');
    },

    // toggle tracks general layer on map and save state in user options
    toggleTracks: function() {
        if (this.map.hasLayer(this.mainLayer)) {
            this.map.removeLayer(this.mainLayer);
            $('#navigation-tracks').removeClass('active');
            $('#map').focus();
        }
        else {
            if (!this.trackListLoaded) {
                this.getTracks();
            }
            this.map.addLayer(this.mainLayer);
            $('#navigation-tracks').addClass('active');
        }
    },

    // add/remove markers from layers considering current filter values
    updateFilterDisplay: function() {
        var startFilter = this.timeFilterController.valueBegin;
        var endFilter = this.timeFilterController.valueEnd;

        var id, layer, i, date;
        for (id in this.trackLayers) {
            date = this.trackLayers[id].date;
            // if it was not filtered, check if it should be removed
            if (this.mapTrackLayers[id].hasLayer(this.trackLayers[id])) {
                if (date && (date < startFilter || date > endFilter)) {
                    this.mapTrackLayers[id].removeLayer(this.trackLayers[id]);
                }
            }
            // if it was filtered, check if it should be added
            else {
                if (date && (date >= startFilter && date <= endFilter)) {
                    this.mapTrackLayers[id].addLayer(this.trackLayers[id]);
                }
            }
        }
    },

    updateMyFirstLastDates: function(updateSlider=false) {
        if (!this.map.hasLayer(this.mainLayer)) {
            this.firstDate = null;
            this.lastDate = null;
        }
        else {
            var id;

            // we update dates only if nothing is currently loading
            for (id in this.mapTrackLayers) {
                if (this.mainLayer.hasLayer(this.mapTrackLayers[id]) && !this.trackLayers[id].loaded) {
                    return;
                }
            }

            var initMinDate = Math.floor(Date.now() / 1000) + 1000000
            var initMaxDate = 0;

            var first = initMinDate;
            var last = initMaxDate;
            for (id in this.mapTrackLayers) {
                if (this.mainLayer.hasLayer(this.mapTrackLayers[id]) && this.trackLayers[id].loaded && this.trackLayers[id].date) {
                    if (this.trackLayers[id].date < first) {
                        first = this.trackLayers[id].date;
                    }
                    if (this.trackLayers[id].date > last) {
                        last = this.trackLayers[id].date;
                    }
                }
            }
            if (first !== initMinDate
                && last !== initMaxDate) {
                this.firstDate = first;
                this.lastDate = last;
            }
            else {
                this.firstDate = null;
                this.lastDate = null;
            }
        }
        if (updateSlider) {
            this.timeFilterController.updateSliderRangeFromController();
            this.timeFilterController.setSliderToMaxInterval();
        }
    },

    saveEnabledTracks: function(additionalIds=[]) {
        var trackList = [];
        var layer;
        for (var id in this.mapTrackLayers) {
            layer = this.mapTrackLayers[id];
            if (this.mainLayer.hasLayer(layer)) {
                trackList.push(id);
            }
        }
        for (var i=0; i < additionalIds.length; i++) {
            trackList.push(additionalIds[i]);
        }
        var trackStringList = trackList.join('|');
        this.optionsController.saveOptionValues({enabledTracks: trackStringList});
        // this is used when tracks are loaded again
        this.optionsController.enabledTracks = trackList;
    },

    showAllTracks: function() {
        if (!this.map.hasLayer(this.mainLayer)) {
            this.toggleTracks();
        }
        for (var id in this.mapTrackLayers) {
            if (!this.mainLayer.hasLayer(this.mapTrackLayers[id])) {
                this.toggleTrack(id);
            }
        }
        this.updateMyFirstLastDates(true);
    },

    hideAllTracks: function() {
        for (var id in this.mapTrackLayers) {
            if (this.mainLayer.hasLayer(this.mapTrackLayers[id])) {
                this.toggleTrack(id);
            }
        }
        this.updateMyFirstLastDates(true);
    },

    removeTrackMap: function(id) {
        this.mainLayer.removeLayer(this.mapTrackLayers[id]);
        this.mapTrackLayers[id].removeLayer(this.trackLayers[id]);
        delete this.mapTrackLayers[id];
        delete this.trackLayers[id];
        delete this.trackColors[id];
        delete this.trackDivIcon[id];
        delete this.tracks[id];

        $('style[track='+id+']').remove();

        $('#track-list > li[track="'+id+'"]').fadeOut('slow', function() {
            $(this).remove();
        });
    },

    addTrackMap: function(track, show=false, pageLoad=false, zoom=false) {
        // color
        var color = track.color || (OCA.Theming ? OCA.Theming.color : '#0082c9');
        this.trackColors[track.id] = color;
        this.trackDivIcon[track.id] = L.divIcon({
            iconAnchor: [12, 25],
            className: 'trackWaypoint trackWaypoint-'+track.id,
            html: ''
        });
        this.tracks[track.id] = track;
        this.tracks[track.id].metadata = $.parseJSON(track.metadata);
        this.tracks[track.id].icon = L.divIcon(L.extend({
            html: '<div class="thumbnail"></div>​',
            className: 'leaflet-marker-track track-marker track-marker-'+track.id
        }, null, {
            iconSize: [this.track_MARKER_VIEW_SIZE, this.track_MARKER_VIEW_SIZE],
            iconAnchor:   [this.track_MARKER_VIEW_SIZE / 2, this.track_MARKER_VIEW_SIZE]
        }));

        this.mapTrackLayers[track.id] = L.featureGroup();
        this.trackLayers[track.id] = L.featureGroup();
        this.trackLayers[track.id].loaded = false;
        this.mapTrackLayers[track.id].addLayer(this.trackLayers[track.id]);

        this.addMenuEntry(track, color);

        // enable if in saved options or if it should be enabled for another reason
        if (show || this.optionsController.enabledTracks.indexOf(track.id) !== -1) {
            this.toggleTrack(track.id, false, pageLoad, zoom);
        }
    },

    addMenuEntry: function(track, color) {
        var name = track.file_name;
        var path = track.file_path;

        // side menu entry
        var imgurl = generateUrl('/svg/core/categories/monitoring?color='+color.replace('#', ''));
        var li = '<li class="track-line" id="'+name+'-track" track="'+track.id+'" name="'+name+'">' +
        '    <a href="#" class="track-name" id="'+name+'-track-name" title="'+escapeHTML(path)+'" style="background-image: url('+imgurl+')">'+name+'</a>' +
        '    <div class="app-navigation-entry-utils">' +
        '        <ul>' +
        '            <li class="app-navigation-entry-utils-menu-button trackMenuButton">' +
        '                <button></button>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-menu">' +
        '        <ul>' +
        '            <li>' +
        '                <a href="#" class="changeTrackColor">' +
        '                    <span class="icon-rename"></span>' +
        '                    <span>'+t('maps', 'Change color')+'</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="zoomTrackButton">' +
        '                    <span class="icon-search"></span>' +
        '                    <span>'+t('maps', 'Zoom to bounds')+'</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="showTrackElevation">' +
        '                    <span class="icon-category-monitoring"></span>' +
        '                    <span>'+t('maps', 'Show track elevation')+'</span>' +
        '                </a>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '</li>';

        var beforeThis = null;
        var that = this;
        if (this.sortOrder === 'name') {
            var nameLower = name.toLowerCase();
            var trackName;
            $('#track-list > li').each(function() {
                trackName = $(this).attr('name');
                if (nameLower.localeCompare(trackName) < 0) {
                    beforeThis = $(this);
                    return false;
                }
            });
        }
        else if (this.sortOrder === 'date') {
            var mtime = parseInt(track.mtime);
            var tmpMtime;
            $('#track-list > li').each(function() {
                tmpMtime = parseInt(that.tracks[$(this).attr('track')].mtime);
                if (mtime > tmpMtime) {
                    beforeThis = $(this);
                    return false;
                }
            });
        }
        if (beforeThis !== null) {
            $(li).insertBefore(beforeThis);
        }
        else {
            $('#track-list').append(li);
        }
    },

    // wipe track list, then add items again
    // take care of enabling selected tracks
    sortTracks: function() {
        $('#track-list').html('');
        var color;
        for (var id in this.tracks) {
            color = this.trackColors[id];
            this.addMenuEntry(this.tracks[id], color);
            // select if necessary
            var mapTrackLayer = this.mapTrackLayers[id];
            var trackLine = $('#track-list > li[track="' + id + '"]');
            var trackName = trackLine.find('.track-name');
            if (this.mainLayer.hasLayer(mapTrackLayer)) {
                trackName.addClass('active');
            }
        }
    },

    getTracks: function() {
        var that = this;
        $('#navigation-tracks').addClass('icon-loading-small');
        var req = {};
        var url = generateUrl('/apps/maps/tracks');
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var i, track, show;
            var getFound = false;
            for (i=0; i < response.length; i++) {
                track = response[i];
                // show'n'zoom track if it was asked with a GET parameter
                show = (getUrlParameter('track') === track.file_path);
                that.addTrackMap(track, show, true, show);
                if (show) {
                    getFound = true;
                }
            }
            // if the asked track wasn't already in track list, load it and zoom!
            if (!getFound && getUrlParameter('track')) {
                OC.Notification.showTemporary(t('maps', 'Track {n} was not found', {n: getUrlParameter('track')}));
            }
            that.trackListLoaded = true;
        }).always(function (response) {
            $('#navigation-tracks').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load tracks'));
        });
    },

    isTrackEnabled: function(id) {
        var mapTrackLayer = this.mapTrackLayers[id];
        return (this.mainLayer.hasLayer(mapTrackLayer));
    },

    toggleTrack: function(id, save=false, pageLoad=false, zoom=false) {
        var trackLayer = this.trackLayers[id];
        if (!trackLayer.loaded) {
            this.loadTrack(id, save, pageLoad, zoom);
        }
        this.toggleMapTrackLayer(id, zoom);
        if (save) {
            this.saveEnabledTracks();
            this.updateMyFirstLastDates(true);
        }
    },

    toggleMapTrackLayer: function(id, zoom=false) {
        var mapTrackLayer = this.mapTrackLayers[id];
        var trackLine = $('#track-list > li[track="'+id+'"]');
        var trackName = trackLine.find('.track-name');
        // hide track
        if (this.mainLayer.hasLayer(mapTrackLayer)) {
            this.mainLayer.removeLayer(mapTrackLayer);
            trackName.removeClass('active');
            $('#map').focus();
        }
        // show track
        else {
            this.mainLayer.addLayer(mapTrackLayer);
            // markers are hard to bring to front
            var that = this;
            this.trackLayers[id].eachLayer(function(l) {
                if (l instanceof L.Marker){
                    l.setZIndexOffset(that.lastZIndex++);
                }
            });
            trackName.addClass('active');
            if (zoom) {
                this.zoomOnTrack(id);
                this.showTrackElevation(id);
            }
        }
    },

    loadTrack: function(id, save=false, pageLoad=false, zoom=false) {
        var that = this;
        $('#track-list > li[track="'+id+'"]').addClass('icon-loading-small');
        var req = {};
        var url = generateUrl('/apps/maps/tracks/'+id);
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.processGpx(id, response.content, response.metadata);
            that.trackLayers[id].loaded = true;
            that.updateMyFirstLastDates(pageLoad);
            if (zoom) {
                that.zoomOnTrack(id);
                that.showTrackElevation(id);
            }
        }).always(function (response) {
            $('#track-list > li[track="'+id+'"]').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load track content'));
        });
    },

    processGpx: function(id, gpx, metadata) {
        var that = this;
        var color;
        var coloredTooltipClass;
        var rgbc;

        this.tracks[id].metadata = $.parseJSON(metadata);

        var gpxp, gpxx;
        try {
            gpxp = $.parseXML(gpx.replace(/version="1.1"/, 'version="1.0"'));
            gpxx = $(gpxp).find('gpx');
        }
        catch (err) {
            OC.Notification.showTemporary(t('maps', 'Failed to parse track {fname}', {fname: this.tracks[id].file_name}));
            this.removeTrackMap(id);
            return;
        }

        // count the number of lines and point
        var nbPoints = gpxx.find('>wpt').length;
        var nbLines = gpxx.find('>trk').length + gpxx.find('>rte').length;

        color = this.trackColors[id];
        this.setTrackCss(id, color);
        coloredTooltipClass = 'tooltip' + id;

        var weight = 4;

        var fileDesc = gpxx.find('>metadata>desc').text();

        var minTrackDate = Math.floor(Date.now() / 1000) + 1000000;
        var date;

        var popupText;

        var wpts = gpxx.find('wpt');
        wpts.each(function() {
            date = that.addWaypoint(id, $(this), coloredTooltipClass);
            minTrackDate = (date < minTrackDate) ? date : minTrackDate;
        });

        var trks = gpxx.find('trk');
        var name, cmt, desc, linkText, linkUrl, popupText, date;
        trks.each(function() {
            name = $(this).find('>name').text();
            cmt = $(this).find('>cmt').text();
            desc = $(this).find('>desc').text();
            linkText = $(this).find('link text').text();
            linkUrl = $(this).find('link').attr('href');
            popupText = that.getLinePopupText(id, name, cmt, desc, linkText, linkUrl);
            $(this).find('trkseg').each(function() {
                date = that.addLine(id, $(this).find('trkpt'), weight, color, name, popupText, coloredTooltipClass);
                minTrackDate = (date < minTrackDate) ? date : minTrackDate;
            });
        });

        var rtes = gpxx.find('rte');
        rtes.each(function() {
            name = $(this).find('>name').text();
            cmt = $(this).find('>cmt').text();
            desc = $(this).find('>desc').text();
            linkText = $(this).find('link text').text();
            linkUrl = $(this).find('link').attr('href');
            popupText = that.getLinePopupText(id, name, cmt, desc, linkText, linkUrl);
            date = that.addLine(id, $(this).find('rtept'), weight, color, name, popupText, coloredTooltipClass);
            minTrackDate = (date < minTrackDate) ? date : minTrackDate;
        });

        this.trackLayers[id].date = minTrackDate;

        // manage track main icon
        // find first point (marker location)
        // then bind tooltip and popup
        var firstWpt = null;
        if (wpts.length > 0) {
            var lat = wpts.first().attr('lat');
            var lon = wpts.first().attr('lon');
            firstWpt = L.latLng(lat, lon);
        }
        var firstLinePoint = null;
        if (trks.length > 0) {
            var trkpt = trks.first().find('trkpt').first();
            if (trkpt) {
                var lat = trkpt.attr('lat');
                var lon = trkpt.attr('lon');
                firstLinePoint = L.latLng(lat, lon);
            }
        }
        if (firstLinePoint === null && rtes.length > 0) {
            var rtept = rtes.first().find('rtept').first();
            if (rtept) {
                var lat = rtept.attr('lat');
                var lon = rtept.attr('lon');
                firstLinePoint = L.latLng(lat, lon);
            }
        }
        var firstPoint = firstLinePoint || firstWpt;

        if (firstPoint) {
            this.tracks[id].marker = L.marker([firstPoint.lat, firstPoint.lng], {
                    icon: this.tracks[id].icon
            });
            this.tracks[id].marker.trackid = id;

            this.tracks[id].marker.on('contextmenu', this.trackMouseRightClick);

            // tooltip
            var tooltipText = this.tracks[id].file_name;
            this.tracks[id].marker.bindTooltip(tooltipText, {
                sticky: false,
                className: coloredTooltipClass + ' leaflet-marker-track-tooltip',
                direction: 'top',
                offset: L.point(0, -16)
            });
            // popup
            popupText = that.getLinePopupText(id, '', '', '', '', '');
            this.tracks[id].popupText = popupText;
            this.trackLayers[id].addLayer(this.tracks[id].marker);
        }
    },

    getTrackMarkerOnClickFunction: function() {
        var _app = this;
        return function(evt) {
            var marker = evt.layer;
            var popupContent = _app.tracks[marker.trackid].popupText;
            marker.unbindPopup();
            _app.map.clickpopup = true;

            var popup = L.popup({
                autoPan: true,
                autoClose: true,
                closeOnClick: true,
                className: 'trackPopup'
            })
                .setLatLng(marker.getLatLng())
                .setContent(popupContent)
                .openOn(_app.map);
            $(popup._closeButton).one('click', function (e) {
                _app.map.clickpopup = null;
            });
        };
    },

    addWaypoint: function(id, elem, coloredTooltipClass) {
        var lat = elem.attr('lat');
        var lon = elem.attr('lon');
        var name = elem.find('name').text();
        var cmt = elem.find('cmt').text();
        var desc = elem.find('desc').text();
        var sym = elem.find('sym').text();
        var ele = elem.find('ele').text();
        var time = elem.find('time').text();
        var linkText = elem.find('link text').text();
        var linkUrl = elem.find('link').attr('href');

        var date = null;
        if (time) {
            date = Date.parse(time)/1000;
        }

        var mm = L.marker(
            [lat, lon],
            {
                icon: this.trackDivIcon[id]
            }
        );
        mm.bindTooltip(brify(name, 20), {
            className: coloredTooltipClass + ' leaflet-marker-track-tooltip',
            direction: 'top',
            offset: L.point(0, -15)
        });
        mm.trackid = id;
        mm.on('contextmenu', this.trackMouseRightClick);

        var popupText = this.getWaypointPopupText(id, name, lat, lon, cmt, desc, ele, linkText, linkUrl, sym);
        mm.bindPopup(popupText);
        this.trackLayers[id].addLayer(mm);
        return date;
    },

    getWaypointPopupText: function(id, name, lat, lon, cmt, desc, ele, linkText, linkUrl, sym) {
        var popupText = '<h3 style="text-align:center;">' + escapeHTML(name) + '</h3><hr/>' +
            t('maps', 'File')+ ' : ' + escapeHTML(this.tracks[id].file_name) + '<br/>';
        if (linkText && linkUrl) {
            popupText = popupText +
                t('maps', 'Link') + ' : <a href="' + escapeHTML(linkUrl) + '" title="' + escapeHTML(linkUrl) + '" target="_blank">'+ escapeHTML(linkText) + '</a><br/>';
        }
        if (ele !== '') {
            popupText = popupText + t('maps', 'Elevation')+ ' : ' +
                escapeHTML(ele) + 'm<br/>';
        }
        popupText = popupText + t('maps', 'Latitude') + ' : '+ parseFloat(lat) + '<br/>' +
            t('maps', 'Longitude') + ' : '+ parseFloat(lon) + '<br/>';
        if (cmt !== '') {
            popupText = popupText +
                t('maps', 'Comment') + ' : '+ escapeHTML(cmt) + '<br/>';
        }
        if (desc !== '') {
            popupText = popupText +
                t('maps', 'Description') + ' : '+ escapeHTML(desc) + '<br/>';
        }
        if (sym !== '') {
            popupText = popupText +
                t('maps', 'Symbol name') + ' : '+ sym;
        }
        return popupText;
    },

    getLinePopupText: function(id, name, cmt, desc, linkText, linkUrl) {
        var meta = this.tracks[id].metadata;
        var url = generateUrl('/apps/files/ajax/download.php');
        var dir = encodeURIComponent(dirname(this.tracks[id].file_path)) || '/';
        var file = encodeURIComponent(this.tracks[id].file_name);
        var dl_url = '"' + url + '?dir=' + dir + '&files=' + file + '"';
        var popupTxt = '<h3 class="trackPopupTitle">' +
            t('maps','File') + ' : <a href=' +
            dl_url + ' title="' + t('maps','download') + ' ' + this.tracks[id].file_path + '"' +
            ' class="getGpx" >' +
            '<i class="fa fa-cloud-download-alt" aria-hidden="true"></i> ' + this.tracks[id].file_name + '</a> ';
        popupTxt = popupTxt + '<button class="drawElevationButton" track="'+id+'"><i class="fa fa-chart-area" aria-hidden="true"></i></button>';
        popupTxt = popupTxt + '</h3>';
        // link url and text
        if (meta.lnktxt) {
            var lt = meta.lnktxt;
            if (!lt) {
                lt = t('maps', 'metadata link');
            }
            popupTxt = popupTxt + '<a class="metadatalink" title="' +
                t('maps', 'metadata link') + '" href="' + meta.lnkurl +
                '" target="_blank">' + lt + '</a>';
        }
        if (meta.trnl && meta.trnl.length > 0) {
            popupTxt = popupTxt + '<ul title="' + t('maps', 'tracks/routes name list') +
                '" class="trackNamesList">';
            for (var z=0; z < meta.trnl.length; z++) {
                var trname = meta.trnl[z];
                if (trname === '') {
                    trname = t('maps', 'no name');
                }
                popupTxt = popupTxt + '<li>' + escapeHTML(trname) + '</li>';
            }
            popupTxt = popupTxt + '</ul>';
        }

        popupTxt = popupTxt +'<table class="popuptable">';
        popupTxt = popupTxt +'<tr>';
        popupTxt = popupTxt +'<td><i class="fa fa-arrows-alt-h" aria-hidden="true"></i> <b>' +
            t('maps','Distance') + '</b></td>';
        if (meta.distance) {
            popupTxt = popupTxt + '<td>' + metersToDistance(meta.distance) + '</td>';
        }
        else{
            popupTxt = popupTxt + '<td>???</td>';
        }
        popupTxt = popupTxt + '</tr><tr>';

        popupTxt = popupTxt + '<td><i class="fa fa-clock" aria-hidden="true"></i> ' +
            t('maps','Duration') + ' </td><td> ' + formatTimeSeconds(meta.duration || 0) + '</td>';
        popupTxt = popupTxt + '</tr><tr>';
        popupTxt = popupTxt + '<td><i class="fa fa-clock" aria-hidden="true"></i> <b>' +
            t('maps','Moving time') + '</b> </td><td> ' + formatTimeSeconds(meta.movtime || 0) + '</td>';
        popupTxt = popupTxt + '</tr><tr>';
        popupTxt = popupTxt + '<td><i class="fa fa-clock" aria-hidden="true"></i> ' +
            t('maps','Pause time') + ' </td><td> ' + formatTimeSeconds(meta.stptime || 0) + '</td>';
        popupTxt = popupTxt + '</tr><tr>';

        var dbs = t('maps', 'no date');
        var dbes = dbs;
        try{
            if (meta.begin !== '' && meta.begin !== -1) {
                var db = new Date(meta.begin * 1000);
                dbs = db.toIsoString();
            }
            if (meta.end !== '' && meta.end !== -1) {
                var dbe = new Date(meta.end * 1000);
                dbes = dbe.toIsoString();
            }
        }
        catch(err) {
        }
        popupTxt = popupTxt +'<td><i class="fa fa-calendar-alt" aria-hidden="true"></i> ' +
            t('maps', 'Begin') + ' </td><td> ' + dbs + '</td>';
        popupTxt = popupTxt +'</tr><tr>';
        popupTxt = popupTxt +'<td><i class="fa fa-calendar-alt" aria-hidden="true"></i> ' +
            t('maps','End') + ' </td><td> ' + dbes + '</td>';
        popupTxt = popupTxt +'</tr><tr>';
        popupTxt = popupTxt +'<td><i class="fa fa-chart-line" aria-hidden="true"></i> <b>' +
            t('maps', 'Cumulative elevation gain') + '</b> </td><td> ' +
            (meta.posel ? metersToElevation(meta.posel) : 'NA') + '</td>';
        popupTxt = popupTxt +'</tr><tr>';
        popupTxt = popupTxt +'<td><i class="fa fa-chart-line" aria-hidden="true"></i> ' +
            t('maps','Cumulative elevation loss') + ' </td><td> ' +
            (meta.negel ? metersToElevation(meta.negel) : 'NA') + '</td>';
        popupTxt = popupTxt +'</tr><tr>';
        popupTxt = popupTxt +'<td><i class="fa fa-chart-area" aria-hidden="true"></i> ' +
            t('maps','Minimum elevation') + ' </td><td> ' +
            ((meta.minel && meta.minel !== -1000) ? metersToElevation(meta.minel) : 'NA') + '</td>';
        popupTxt = popupTxt +'</tr><tr>';
        popupTxt = popupTxt +'<td><i class="fa fa-chart-area" aria-hidden="true"></i> ' +
            t('maps','Maximum elevation') + ' </td><td> ' +
            ((meta.maxel && meta.maxel !== -1000) ? metersToElevation(meta.maxel) : 'NA') + '</td>';
        popupTxt = popupTxt +'</tr><tr>';
        popupTxt = popupTxt +'<td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> <b>' +
            t('maps','Maximum speed') + '</b> </td><td> ';
        if (meta.maxspd) {
            popupTxt = popupTxt + kmphToSpeed(meta.maxspd);
        }
        else{
            popupTxt = popupTxt + 'NA';
        }
        popupTxt = popupTxt + '</td>';
        popupTxt = popupTxt + '</tr><tr>';

        popupTxt = popupTxt + '<td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> ' +
            t('maps','Average speed') + ' </td><td> ';
        if (meta.avgspd) {
            popupTxt = popupTxt + kmphToSpeed(meta.avgspd);
        }
        else{
            popupTxt = popupTxt + 'NA';
        }
        popupTxt = popupTxt + '</td>';
        popupTxt = popupTxt + '</tr><tr>';

        popupTxt = popupTxt + '<td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> <b>' +
            t('maps','Moving average speed') + '</b> </td><td> ';
        if (meta.movavgspd) {
            popupTxt = popupTxt + kmphToSpeed(meta.movavgspd);
        }
        else{
            popupTxt = popupTxt + 'NA';
        }
        popupTxt = popupTxt + '</td></tr>';

        popupTxt = popupTxt + '<tr><td><i class="fa fa-tachometer-alt" aria-hidden="true"></i> <b>' +
            t('maps','Moving average pace') + '</b> </td><td> ';
        if (meta.movpace) {
            popupTxt = popupTxt + minPerKmToPace(meta.movpace);
        }
        else{
            popupTxt = popupTxt + 'NA';
        }
        popupTxt = popupTxt + '</td></tr>';
        popupTxt = popupTxt + '</table>';

        return popupTxt;
    },

    addLine: function(id, points, weight, color, name, popupText, coloredTooltipClass) {
        var lat, lon, ele, time;
        var that = this;
        var latlngs = [];
        // get first date
        var date = null;
        if (points.length > 0) {
            var p = points.first();
            time = p.find('time').text();
            if (time) {
                date = Date.parse(time)/1000;
            }
        }
        // build line
        points.each(function() {
            lat = $(this).attr('lat');
            lon = $(this).attr('lon');
            if (!lat || !lon) {
                return;
            }
            ele = $(this).find('ele').text();
            time = $(this).find('time').text();
            if (ele !== '') {
                latlngs.push([lat, lon, ele]);
            }
            else{
                latlngs.push([lat, lon]);
            }
        });
        var l = L.polyline(latlngs, {
            weight: weight,
            opacity : 1,
            className: 'poly'+id,
        });
        l.line = true;
        l.bindPopup(
            popupText,
            {
                autoPan: true,
                autoClose: true,
                closeOnClick: true,
                className: 'trackPopup'
            }
        );
        var tooltipText = this.tracks[id].file_name;
        if (this.tracks[id].file_name !== name) {
            tooltipText = tooltipText + '<br/>' + escapeHTML(name);
        }
        l.bindTooltip(tooltipText, {
            sticky: true,
            className: coloredTooltipClass + ' leaflet-marker-track-tooltip',
            direction: 'top'
        });
        // border layout
        var bl;
        bl = L.polyline(latlngs,
            {opacity:1, weight: parseInt(weight * 1.6), color: 'black'});
        bl.bindPopup(
            popupText,
            {
                autoPan: true,
                autoClose: true,
                closeOnClick: true,
                className: 'trackPopup'
            }
        );
        this.trackLayers[id].addLayer(bl);
        this.trackLayers[id].addLayer(l);
        bl.on('mouseover', function() {
            that.trackLayers[id].bringToFront();
        });
        bl.on('mouseout', function() {
        });
        bl.bindTooltip(tooltipText, {
            sticky: true,
            className: coloredTooltipClass + ' leaflet-marker-track-tooltip',
            direction: 'top'
        });

        l.on('mouseover', function() {
            that.trackLayers[id].bringToFront();
        });
        l.on('mouseout', function() {
        });
        l.trackid = id;
        l.on('contextmenu', this.trackMouseRightClick);
        bl.trackid = id;
        bl.on('contextmenu', this.trackMouseRightClick);

        return date;
    },

    trackMouseRightClick: function(e) {
        var that = this;
        var id = e.target.trackid;

        var yOffset = 5;
        if (e.target instanceof L.Marker) {
            yOffset = -10;
        }
        this._map.clickpopup = true;
        var popupContent = this._map.tracksController.getTrackContextPopupContent(id);
        var popup = L.popup({
            closeOnClick: true,
            className: 'popovermenu open popupMarker',
            offset: L.point(-5, yOffset)
        })
            .setLatLng(e.latlng)
            .setContent(popupContent)
            .openOn(this._map);
        $(popup._closeButton).one('click', function (e) {
            that._map.clickpopup = null;
        });
    },

    getTrackContextPopupContent: function(id) {
        var colorText = t('maps', 'Change color');
        var elevationText = t('maps', 'Show elevation');
        var res =
            '<ul trackid="' + id + '">' +
            '   <li>' +
            '       <button class="icon-rename contextChangeTrackColor">' +
            '           <span>' + colorText + '</span>' +
            '       </button>' +
            '   </li>' +
            '   <li>' +
            '       <button class="icon-category-monitoring contextShowElevation">' +
            '           <span>' + elevationText + '</span>' +
            '       </button>' +
            '   </li>' +
            '</ul>';
        return res;
    },

    zoomOnTrack: function(id) {
        if (this.mainLayer.hasLayer(this.mapTrackLayers[id])) {
            var bounds = this.mapTrackLayers[id].getBounds();
            if (bounds && bounds.constructor === Object && Object.keys(bounds).length !== 0) {
                this.map.fitBounds(this.mapTrackLayers[id].getBounds(), {padding: [30, 30], maxZoom: 17});
                this.mapTrackLayers[id].bringToFront();
                // markers are hard to bring to front
                var that = this;
                this.trackLayers[id].eachLayer(function(l) {
                    if (l instanceof L.Marker){
                        l.setZIndexOffset(that.lastZIndex++);
                    }
                });
            }
        }
    },

    askChangeTrackColor: function(id) {
        this.changingColorOf = id;
        var currentColor = this.trackColors[id];
        $('#trackcolorinput').val(currentColor);
        $('#trackcolorinput').click();
    },

    okColor: function() {
        var color = $('#trackcolorinput').val();
        var id = this.changingColorOf;
        this.trackColors[id] = color;
        this.changeTrackColor(id, color);
    },

    changeTrackColor: function(id, color) {
        var that = this;
        $('#track-list > li[track="'+id+'"]').addClass('icon-loading-small');
        var req = {
            color: color
        };
        var url = generateUrl('/apps/maps/tracks/'+id);
        $.ajax({
            type: 'PUT',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var imgurl = generateUrl('/svg/core/categories/monitoring?color='+color.replace('#', ''));
            $('#track-list > li[track='+id+'] .track-name').attr('style', 'background-image: url('+imgurl+')');

            that.setTrackCss(id, color);
        }).always(function (response) {
            $('#track-list > li[track="'+id+'"]').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to change track color'));
        });
    },

    setTrackCss: function(id, color) {
        $('style[track='+id+']').remove();

        var imgurl = generateUrl('/svg/core/categories/monitoring?color='+color.replace('#', ''));
        $('<style track="' + id + '">' +
            '.tooltip' + id + ' { ' +
            'border: 2px solid ' + color + ';' +
            ' }' +
            '.poly' + id + ' {' +
            'stroke: ' + color + ';' +
            '}' +
            '.trackWaypoint-'+id+' { ' +
            'background-color: '+color+';}' +
            '.track-marker-'+id+' { ' +
            'border-color: '+color+';}' +
            '.track-marker-'+id+'::after {' +
            'border-color: '+color+' transparent !important;}' +
            '.track-marker-'+id+' .thumbnail { ' +
            'background-image: url(' + imgurl + ');}' +
            '</style>').appendTo('body');
    },

    showTrackElevation: function(id) {
        this.clearElevationControl();
        this.zoomOnTrack(id);
        var el = L.control.elevation({
            position: 'bottomleft',
            height: 100,
            width: 700,
            margins: {
                top: 10,
                right: 40,
                bottom: 23,
                left: 60
            },
            //collapsed: true,
            theme: 'steelblue-theme'
        });
        el.addTo(this.map);

        var layers = this.trackLayers[id].getLayers();
        var data;
        for (var i=0; i < layers.length; i++) {
            if (layers[i].line) {
                data = layers[i].toGeoJSON();
                el.addData(data, layers[i]);
            }
        }
        this.closeElevationButton.addTo(this.map);

        this.elevationControl = el;
    },

    clearElevationControl: function() {
        if (this.elevationControl !== null) {
            this.elevationControl.clear();
            this.elevationControl.remove();
            this.elevationControl = null;
            this.closeElevationButton.remove();
        }
    },

    getAutocompData: function() {
        var that = this;
        var track, trackid;
        var data = [];
        if (this.map.hasLayer(this.mainLayer)) {
            for (trackid in this.tracks) {
                // no need for lat/lng here, track will just be enabled or zoomed
                track = this.tracks[trackid];
                data.push({
                    type: 'track',
                    id: trackid,
                    label: track.file_name,
                    value: track.file_name
                });
            }
        }
        return data;
    },

}

export default TracksController;
