function TracksController(optionsController, timeFilterController) {
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;

    this.mainLayer = null;
    // indexed by track id
    // those actually added to map, those which get toggled
    this.mapTrackLayers = {};
    // layers which actually contain lines/waypoints, those which get filtered
    this.trackLayers = {};
    this.track = {};

    this.firstDate = null;
    this.lastDate = null;

    // used by optionsController to know if tracks loading
    // was done before or after option restoration
    this.trackListLoaded = false;
}

TracksController.prototype = {

    // set up favorites-related UI stuff
    initController : function(map) {
        this.map = map;
        this.mainLayer = L.featureGroup();
        var that = this;
        // UI events
        // click on menu buttons
        $('body').on('click', '.tracksMenuButton, .trackMenuButton', function(e) {
            var wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });
        // click on a track name : zoom to bounds
        $('body').on('click', '.track-line .track-name', function(e) {
            var id = $(this).parent().attr('track');
            that.zoomOnTrack(id);
        });
        // toggle a track
        $('body').on('click', '.toggleTrackButton', function(e) {
            var id = $(this).parent().parent().parent().attr('track');
            that.toggleTrack(id, true);
        });
        // remove a track
        $('body').on('click', '.removeTrack', function(e) {
            var id = parseInt($(this).parent().parent().parent().parent().attr('track'));
            that.removeTrackDB(id);
        });
        // remove all tracks
        $('body').on('click', '#remove-all-tracks', function(e) {
            that.removeAllTracksDB();
        });
        // show/hide all tracks
        $('body').on('click', '#select-all-tracks', function(e) {
            that.showAllTracks();
            var trackStringList = Object.keys(that.trackLayers).join('|');
            that.optionsController.saveOptionValues({enabledTracks: trackStringList});
            that.optionsController.enabledTracks = trackStringList;
            that.optionsController.saveOptionValues({tracksEnabled: that.map.hasLayer(that.mainLayer)});
        });
        $('body').on('click', '#select-no-tracks', function(e) {
            that.hideAllTracks();
            var trackStringList = '';
            that.optionsController.saveOptionValues({enabledTracks: trackStringList});
            that.optionsController.enabledTracks = trackStringList;
            that.optionsController.saveOptionValues({tracksEnabled: that.map.hasLayer(that.mainLayer)});
        });
        // click on + button
        $('body').on('click', '#addTrackButton', function(e) {
            OC.dialogs.filepicker(
                t('maps', 'Load gpx file'),
                function(targetPath) {
                    that.addTracksDB(targetPath);
                },
                true,
                'application/gpx+xml',
                true
            );
        });
        // click on add directory button
        $('body').on('click', '#add-track-folder', function(e) {
            OC.dialogs.filepicker(
                t('maps', 'Load gpx files from directory'),
                function(targetPath) {
                    that.addTrackDirectoryDB(targetPath || '/');
                },
                false,
                'httpd/unix-directory',
                true
            );
        });
        // toggle tracks
        $('body').on('click', '#toggleTracksButton', function(e) {
            that.toggleTracks();
            that.optionsController.saveOptionValues({tracksEnabled: that.map.hasLayer(that.mainLayer)});
            that.updateMyFirstLastDates();
        });
        // expand track list
        $('body').on('click', '#navigation-tracks > a', function(e) {
            that.toggleTrackList();
            that.optionsController.saveOptionValues({trackListShow: $('#navigation-tracks').hasClass('open')});
        });
        $('body').on('click', '#navigation-tracks', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-tracks') {
                that.toggleTrackList();
                that.optionsController.saveOptionValues({trackListShow: $('#navigation-tracks').hasClass('open')});
            }
        });
    },

    // expand or fold categories in sidebar
    toggleTrackList: function() {
        $('#navigation-tracks').toggleClass('open');
    },

    // toggle tracks general layer on map and save state in user options
    toggleTracks: function() {
        if (this.map.hasLayer(this.mainLayer)) {
            this.map.removeLayer(this.mainLayer);
            // color of the eye
            $('#toggleTracksButton button').addClass('icon-toggle').attr('style', '');
        }
        else {
            this.map.addLayer(this.mainLayer);
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            $('#toggleTracksButton button').removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
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

    updateMyFirstLastDates: function(pageLoad=false) {
        if (!this.map.hasLayer(this.mainLayer)) {
            this.firstDate = null;
            this.lastDate = null;
            return;
        }

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
        if (pageLoad) {
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

    restoreTracksState: function(enabledTrackList) {
        var id;
        for (var i=0; i < enabledTrackList.length; i++) {
            id = enabledTrackList[i];
            if (this.mapTrackLayers.hasOwnProperty(id)) {
                this.toggleTrack(id, false, true);
            }
        }
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
        this.updateMyFirstLastDates();
    },

    hideAllTracks: function() {
        for (var id in this.mapTrackLayers) {
            if (this.mainLayer.hasLayer(this.mapTrackLayers[id])) {
                this.toggleTrack(id);
            }
        }
        this.updateMyFirstLastDates();
    },

    removeTrackDB: function(id) {
        var that = this;
        $('#track-list > li[track="'+id+'"]').addClass('icon-loading-small');
        var req = {};
        var url = OC.generateUrl('/apps/maps/tracks/'+id);
        $.ajax({
            type: 'DELETE',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.removeTrackMap(id);
            that.saveEnabledTracks();
        }).always(function (response) {
            $('#track-list > li[track="'+id+'"]').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to remove track'));
        });
    },

    removeAllTracksDB: function() {
        var that = this;
        $('#navigation-tracks').addClass('icon-loading-small');
        var req = {
            ids: Object.keys(this.trackLayers)
        };
        var url = OC.generateUrl('/apps/maps/tracks');
        $.ajax({
            type: 'DELETE',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            for (var id in that.trackLayers) {
                that.removeTrackMap(id);
            }
            that.saveEnabledTracks();
        }).always(function (response) {
            $('#navigation-tracks').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to remove track'));
        });
    },

    removeTrackMap: function(id) {
        this.mainLayer.removeLayer(this.mapTrackLayers[id]);
        this.mapTrackLayers[id].removeLayer(this.trackLayers[id]);
        delete this.mapTrackLayers[id];
        delete this.trackLayers[id];
        delete this.track[id];

        $('#track-list > li[track="'+id+'"]').fadeOut('slow', function() {
            $(this).remove();
        });
    },

    addTrackDirectoryDB: function(path) {
        var that = this;
        $('#navigation-tracks').addClass('icon-loading-small');
        var req = {
            path: path
        };
        var url = OC.generateUrl('/apps/maps/tracks-directory');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            // show main layer if needed
            if (!that.map.hasLayer(that.mainLayer)) {
                that.toggleTracks();
            }
            var ids = [];
            for (var i=0; i < response.length; i++) {
                that.addTrackMap(response[i], true);
                ids.push(response[i].id);
            }
            that.saveEnabledTracks(ids);
            that.optionsController.saveOptionValues({tracksEnabled: true});
        }).always(function (response) {
            $('#navigation-tracks').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to add track directory'));
        });
    },

    addTracksDB: function(pathList) {
        var that = this;
        $('#navigation-tracks').addClass('icon-loading-small');
        var req = {
            pathList: pathList
        };
        var url = OC.generateUrl('/apps/maps/tracks');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            // show main layer if needed
            if (!that.map.hasLayer(that.mainLayer)) {
                that.toggleTracks();
            }
            var ids = [];
            for (var i=0; i < response.length; i++) {
                that.addTrackMap(response[i], true);
                ids.push(response[i].id);
            }
            that.saveEnabledTracks(ids);
            that.optionsController.saveOptionValues({tracksEnabled: true});
        }).always(function (response) {
            $('#navigation-tracks').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to add tracks'));
        });
    },

    addTrackMap: function(track, show=false, pageLoad=false) {
        // color
        var color = track.color || OCA.Theming.color;

        this.mapTrackLayers[track.id] = L.featureGroup();
        this.trackLayers[track.id] = L.featureGroup();
        this.trackLayers[track.id].loaded = false;
        this.mapTrackLayers[track.id].addLayer(this.trackLayers[track.id]);

        var name = track.file_name;

        // side menu entry
        var imgurl = OC.generateUrl('/svg/core/actions/address?color='+color.replace('#', ''));
        var li = '<li class="track-line" id="'+name+'-track" track="'+track.id+'" name="'+name+'">' +
        '    <a href="#" class="track-name" id="'+name+'-track-name" style="background-image: url('+imgurl+')">'+name+'</a>' +
        '    <div class="app-navigation-entry-utils">' +
        '        <ul>' +
        '            <li class="app-navigation-entry-utils-menu-button toggleTrackButton" title="'+t('maps', 'Toggle track')+'">' +
        '                <button class="icon-toggle"></button>' +
        '            </li>' +
        '            <li class="app-navigation-entry-utils-menu-button trackMenuButton">' +
        '                <button></button>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-menu">' +
        '        <ul>' +
        '            <li>' +
        '                <a href="#" class="removeTrack">' +
        '                    <span class="icon-close"></span>' +
        '                    <span>'+t('maps', 'Remove')+'</span>' +
        '                </a>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '</li>';

        var beforeThis = null;
        var nameLower = name.toLowerCase();
        $('#track-list > li').each(function() {
            trackName = $(this).attr('name');
            if (nameLower.localeCompare(trackName) < 0) {
                beforeThis = $(this);
                return false;
            }
        });
        if (beforeThis !== null) {
            $(li).insertBefore(beforeThis);
        }
        else {
            $('#track-list').append(li);
        }

        // enable if in saved options or if it should be enabled for another reason
        if (show || this.optionsController.enabledTracks.indexOf(track.id) !== -1) {
            this.toggleTrack(track.id, false, pageLoad);
        }
    },

    getTracks: function() {
        var that = this;
        $('#navigation-tracks').addClass('icon-loading-small');
        var req = {};
        var url = OC.generateUrl('/apps/maps/tracks');
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var i, track;
            for (i=0; i < response.length; i++) {
                track = response[i];
                that.addTrackMap(track, false, true);
            }
            that.trackListLoaded = true;
        }).always(function (response) {
            $('#navigation-tracks').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load tracks'));
        });
    },

    toggleTrack: function(id, save=false, pageLoad=false) {
        var trackLayer = this.trackLayers[id];
        if (!trackLayer.loaded) {
            this.loadTrack(id, save, pageLoad);
        }
        this.toggleMapTrackLayer(id);
        if (save) {
            this.saveEnabledTracks();
            this.updateMyFirstLastDates();
        }
    },

    toggleMapTrackLayer: function(id) {
        var mapTrackLayer = this.mapTrackLayers[id];
        var eyeButton = $('#track-list > li[track="'+id+'"] .toggleTrackButton button');
        // hide track
        if (this.mainLayer.hasLayer(mapTrackLayer)) {
            this.mainLayer.removeLayer(mapTrackLayer);
            // color of the eye
            eyeButton.addClass('icon-toggle').attr('style', '');
        }
        // show track
        else {
            this.mainLayer.addLayer(mapTrackLayer);
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            eyeButton.removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
        }
    },

    loadTrack: function(id, save=false, pageLoad=false) {
        var that = this;
        $('#track-list > li[track="'+id+'"]').addClass('icon-loading-small');
        var req = {};
        var url = OC.generateUrl('/apps/maps/tracks/'+id);
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.processGpx(id, response, that.trackLayers[id]);
            that.trackLayers[id].loaded = true;
            that.updateMyFirstLastDates(pageLoad);
        }).always(function (response) {
            $('#track-list > li[track="'+id+'"]').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load track content'));
        });
    },

    processGpx: function(id, gpx, layerGroup) {
        var that = this;
        var color;
        var coloredTooltipClass;
        var rgbc;

        var gpxp = $.parseXML(gpx.replace(/version="1.1"/, 'version="1.0"'));
        var gpxx = $(gpxp).find('gpx');

        // count the number of lines and point
        var nbPoints = gpxx.find('>wpt').length;
        var nbLines = gpxx.find('>trk').length + gpxx.find('>rte').length;

        color = '#0000EE';
        rgbc = hexToRgb(color);
        $('<style track="' + id + '">.tooltip' + color.replace('#','') + ' { ' +
            'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.5);' +
            'color: black; font-weight: bold;' +
            ' }</style>').appendTo('body');
        coloredTooltipClass = 'tooltip' + color.replace('#','');

        var weight = 4;

        var fileDesc = gpxx.find('>metadata>desc').text();

        var minTrackDate = Math.floor(Date.now() / 1000) + 1000000;
        var date;

        gpxx.find('wpt').each(function() {
            date = that.addWaypoint(id, $(this), layerGroup, coloredTooltipClass);
            minTrackDate = (date < minTrackDate) ? date : minTrackDate;
        });

        gpxx.find('trk').each(function() {
            name = $(this).find('>name').text();
            cmt = $(this).find('>cmt').text();
            desc = $(this).find('>desc').text();
            linkText = $(this).find('link text').text();
            linkUrl = $(this).find('link').attr('href');
            $(this).find('trkseg').each(function() {
                date = that.addLine(id, $(this).find('trkpt'), layerGroup, weight, color, name, cmt, desc, linkText, linkUrl, coloredTooltipClass);
                minTrackDate = (date < minTrackDate) ? date : minTrackDate;
            });
        });

        // ROUTES
        gpxx.find('rte').each(function() {
            name = $(this).find('>name').text();
            cmt = $(this).find('>cmt').text();
            desc = $(this).find('>desc').text();
            linkText = $(this).find('link text').text();
            linkUrl = $(this).find('link').attr('href');
            date = that.addLine(id, $(this).find('rtept'), layerGroup, weight, color, name, cmt, desc, linkText, linkUrl, coloredTooltipClass);
            minTrackDate = (date < minTrackDate) ? date : minTrackDate;
        });

        layerGroup.date = minTrackDate;
    },

    addWaypoint: function(id, elem, layerGroup, coloredTooltipClass) {
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
            [lat, lon]
            //{
            //    icon: symbolIcons[waypointStyle]
            //}
        );
        mm.bindTooltip(brify(name, 20), {className: coloredTooltipClass});

        var popupText = '<h3 style="text-align:center;">' + escapeHTML(name) + '</h3><hr/>' +
            t('maps', 'Track')+ ' : ' + escapeHTML(id) + '<br/>';
        if (linkText && linkUrl) {
            popupText = popupText +
                t('maps', 'Link') + ' : <a href="' + escapeHTML(linkUrl) + '" title="' + escapeHTML(linkUrl) + '" target="_blank">'+ escapeHTML(linkText) + '</a><br/>';
        }
        if (ele !== '') {
            popupText = popupText + t('maps', 'Elevation')+ ' : ' +
                escapeHTML(ele) + 'm<br/>';
        }
        var popupText = popupText + t('maps', 'Latitude') + ' : '+ lat + '<br/>' +
            t('maps', 'Longitude') + ' : '+ lon + '<br/>';
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
        mm.bindPopup(popupText);
        layerGroup.addLayer(mm);
        return date;
    },

    addLine: function(id, points, layerGroup, weight, color, name, cmt, desc, linkText, linkUrl, coloredTooltipClass) {
        var lat, lon, ele, time;
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
            color: color,
        });
        var popupText = 'Track '+id+'<br/>';
        if (cmt !== '') {
            popupText = popupText + '<p class="combutton" combutforfeat="' +
                escapeHTML(id) + escapeHTML(name) +
                '" style="margin:0; cursor:pointer;">' + t('maps', 'Comment') +
                ' <i class="fa fa-expand"></i></p>' +
                '<p class="comtext" style="display:none; margin:0; cursor:pointer;" comforfeat="' +
                escapeHTML(id) + escapeHTML(name) + '">' +
                escapeHTML(cmt) + '</p>';
        }
        if (desc !== '') {
            popupText = popupText + '<p class="descbutton" descbutforfeat="' +
                escapeHTML(id) + escapeHTML(name) +
                '" style="margin:0; cursor:pointer;">Description <i class="fa fa-expand"></i></p>' +
                '<p class="desctext" style="display:none; margin:0; cursor:pointer;" descforfeat="' +
                escapeHTML(id) + escapeHTML(name) + '">' +
                escapeHTML(desc) + '</p>';
        }
        linkHTML = '';
        if (linkText && linkUrl) {
            linkHTML = '<a href="' + escapeHTML(linkUrl) + '" title="' + escapeHTML(linkUrl) + '" target="_blank">' + escapeHTML(linkText) + '</a>';
        }
        popupText = popupText.replace('<li>' + escapeHTML(name) + '</li>',
            '<li><b>' + escapeHTML(name) + ' (' + linkHTML + ')</b></li>');
        l.bindPopup(
            popupText,
            {
                autoPan: true,
                autoClose: true,
                closeOnClick: true
            }
        );
        var tooltipText = id;
        if (id !== name) {
            tooltipText = tooltipText + '<br/>' + escapeHTML(name);
        }
        l.bindTooltip(tooltipText, {sticky: true, className: coloredTooltipClass});
        // border layout
        var bl;
        bl = L.polyline(latlngs,
            {opacity:1, weight: parseInt(weight * 1.6), color: 'black'});
        bl.bindPopup(
            popupText,
            {
                autoPan: true,
                autoClose: true,
                closeOnClick: true
            }
        );
        layerGroup.addLayer(bl);
        layerGroup.addLayer(l);
        bl.on('mouseover', function() {
            layerGroup.bringToFront();
        });
        bl.on('mouseout', function() {
        });
        bl.bindTooltip(tooltipText, {sticky: true, className: coloredTooltipClass});

        l.on('mouseover', function() {
            layerGroup.bringToFront();
        });
        l.on('mouseout', function() {
        });

        return date;
    },

    zoomOnTrack: function(id) {
        if (this.mainLayer.hasLayer(this.mapTrackLayers[id])) {
            this.map.fitBounds(this.mapTrackLayers[id].getBounds(), {padding: [30, 30]});
        }
    }

}
