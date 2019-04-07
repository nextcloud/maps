function TracksController(optionsController, timeFilterController) {
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;

    this.mainLayer = null;
    // indexed by track file id
    this.trackLayers = {};

    this.firstDate = null;
    this.lastDate = null;

    // used by optionsController to know if tracks loading
    // was done before or after option restoration
    this.tracksLoaded = false;
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
            var track = $(this).parent().parent().parent().attr('track');
            that.toggleTrack(track);
            that.saveEnabledTracks();
            that.updateMyFirstLastDates();
        });
        // show/hide all tracks
        $('body').on('click', '#select-all-tracks', function(e) {
            that.showAllTracks();
            that.saveEnabledTracks();
            that.optionsController.saveOptionValues({tracksEnabled: that.map.hasLayer(that.mainLayer)});
        });
        $('body').on('click', '#select-no-tracks', function(e) {
            that.hideAllTracks();
            that.saveEnabledTracks();
            that.optionsController.saveOptionValues({tracksEnabled: that.map.hasLayer(that.mainLayer)});
        });
        // click on + button
        $('body').on('click', '#addTrackButton', function(e) {
            OC.dialogs.filepicker(
                t('maps', 'Load gpx file'),
                function(targetPath) {
                    that.addTrack(targetPath);
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


}
