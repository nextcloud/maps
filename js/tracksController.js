function TracksController(optionsController, timeFilterController) {
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;

    this.mainLayer = null;
    // indexed by track id
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
            var track = $(this).text();
            that.zoomOnTrack(track);
        });
        // toggle a track
        $('body').on('click', '.toggleTrackButton', function(e) {
            var id = $(this).parent().parent().parent().attr('track');
            that.toggleTrack(id, true);
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
                    that.addTrackDB(targetPath);
                },
                false,
                'application/gpx+xml',
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

    updateTimeFilterRange: function() {
        this.updateMyFirstLastDates();
        this.timeFilterController.updateSliderRangeFromController();
    },

    updateMyFirstLastDates: function() {
        if (!this.map.hasLayer(this.mainLayer)) {
            this.firstDate = null;
            this.lastDate = null;
            return;
        }

        var id;

        var initMinDate = Math.floor(Date.now() / 1000) + 1000000
        var initMaxDate = 0;

        var first = initMinDate;
        var last = initMaxDate;
        for (id in this.trackLayers) {
            if (this.mainLayer.hasLayer(this.trackLayers[id])) {
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
    },

    saveEnabledTracks: function() {
        var trackList = [];
        var layer;
        for (var id in this.trackLayers) {
            layer = this.trackLayers[id];
            if (this.mainLayer.hasLayer(layer)) {
                trackList.push(id);
            }
        }
        var trackStringList = trackList.join('|');
        this.optionsController.saveOptionValues({enabledTracks: trackStringList});
        // this is used when tracks are loaded again
        this.optionsController.enabledTracks = trackStringList;
    },

    restoreTracksState: function(enabledTrackList) {
        var id;
        for (var i=0; i < enabledTrackList.length; i++) {
            id = enabledTrackList[i];
            if (this.trackLayers.hasOwnProperty(id)) {
                this.toggleTrack(id);
            }
        }
        this.updateTimeFilterRange();
        this.timeFilterController.setSliderToMaxInterval();
    },

    showAllTracks: function() {
        if (!this.map.hasLayer(this.mainLayer)) {
            this.toggleTracks();
        }
        for (var id in this.trackLayers) {
            if (!this.mainLayer.hasLayer(this.trackLayers[id])) {
                this.toggleTrack(id);
            }
        }
        this.updateMyFirstLastDates();
    },

    hideAllTracks: function() {
        for (var id in this.trackLayers) {
            if (this.mainLayer.hasLayer(this.trackLayers[id])) {
                this.toggleTrack(id);
            }
        }
        this.updateMyFirstLastDates();
    },

    addTrackDB: function(path) {
        var that = this;
        $('#navigation-tracks').addClass('icon-loading-small');
        var req = {
            path: path
        };
        var url = OC.generateUrl('/apps/maps/tracks');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.addTrackMap(response, true);
            that.updateTimeFilterRange();
        }).always(function (response) {
            $('#navigation-tracks').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to add track'));
        });
    },

    addTrackMap: function(track, show=false) {
        // color
        var color = track.color || OCA.Theming.color;

        this.trackLayers[track.id] = L.featureGroup();
        this.trackLayers[track.id].loaded = false;

        var name = basename(track.file_path);

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
            // save if state was not restored
            this.toggleTrack(track.id, show);
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
                that.addTrackMap(track);
            }
            that.trackListLoaded = true;
            that.updateTimeFilterRange();
            that.timeFilterController.setSliderToMaxInterval();
        }).always(function (response) {
            $('#navigation-tracks').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load tracks'));
        });
    },

    toggleTrack: function(id, save=false) {
        var trackLayer = this.trackLayers[id];
        if (!trackLayer.loaded) {
            this.loadTrack(id, save);
        }
        else {
            this.toggleTrackLayer(id);
            if (save) {
                this.saveEnabledTracks();
                this.updateMyFirstLastDates();
            }
        }
    },

    toggleTrackLayer: function(id) {
        var trackLayer = this.trackLayers[id];
        var eyeButton = $('#track-list > li[track="'+id+'"] .toggleTrackButton button');
        // hide track
        if (this.mainLayer.hasLayer(trackLayer)) {
            this.mainLayer.removeLayer(trackLayer);
            // color of the eye
            eyeButton.addClass('icon-toggle').attr('style', '');
        }
        // show track
        else {
            this.mainLayer.addLayer(trackLayer);
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            eyeButton.removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
        }
    },

    loadTrack: function(id, save=false) {
        var that = this;
        $('#track-list > li[track="'+id+'"] .toggleTrackButton button').addClass('icon-loading-small');
        var req = {};
        var url = OC.generateUrl('/apps/maps/tracks/'+id);
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.trackLayers[id].loaded = true;
            that.toggleTrack(id, save);
        }).always(function (response) {
            $('#track-list > li[track="'+id+'"] .toggleTrackButton button').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load track content'));
        });
    },

}
