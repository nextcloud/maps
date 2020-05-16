import {generateUrl} from '@nextcloud/router';

function MyMapsController (optionsController, favoritesController, photosController, tracksController) {
    this.optionsController = optionsController;
    this.favoritesController = favoritesController;
    this.photosController = photosController;
    this.tracksController = tracksController;

    this.myMapsEnabled = false;
    this.myMapsList = [
        {
            name: "Map0",
            path: "/",
            enabled: false,
            loaded: false,
            id: 3,
            color: "#0503ff"
        },
        {
            name: "Map1",
            path: "/",
            enabled: false,
            loaded: false,
            id: 7,
            color: "#ff0335"
        },
        {
            name: "Map2",
            path: "/",
            enabled: false,
            loaded: false,
            id: 13,
            color: "#22d016"
        }
    ];
    this.myMapsListLoaded = false;
}

MyMapsController.prototype = {

    // set up favorites-related UI stuff
    initController: function (map) {
        this.map = map;
        this.mainLayer = L.featureGroup();
        var that = this;
        var body = $('body');
        var n = $('#navigation-my-maps');
        // toggle my-maps
        body.on('click', '#navigation-my-maps > a', function (e) {
            that.toggleMyMaps();
            that.optionsController.saveOptionValues({myMapsEnabled: that.myMapsEnabled});
            if (!n.hasClass('open')) {
                that.toggleMyMapsList();
                that.optionsController.saveOptionValues({myMapsListShow: n.hasClass('open')});
            }
        });
        // expand track list
        body.on('click', '#navigation-my-maps', function (e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-my-maps') {
                that.toggleMyMapsList();
                that.optionsController.saveOptionValues({myMapsListShow: n.hasClass('open')});
            }
        });
        body.on('click', '.my-maps-item .my-maps-name', function(e) {
            var id = $(this).parent().attr('map');
            that.toggleMyMap(
                that.myMapsList.find(function(m) {
                        return m.id == id;
                    }
                )
            );
        });
    },

    // expand or fold my maps list in sidebar
    toggleMyMapsList: function () {
        $('#navigation-my-maps').toggleClass('open');
    },

    // toggle my maps general layer on map and save state in user options
    toggleMyMaps: function () {
        var that = this;
        var n = $('#navigation-my-maps');
        if (this.myMapsEnabled) {
            this.hideAllMyMaps()
            n.removeClass('active');
            $('#map').focus();
            this.myMapsEnabled = false;
        } else {
            if (!this.myMapsListLoaded) {
                this.getMyMaps();
            }
            n.addClass('open');
            n.addClass('active');
            this.myMapsEnabled = true;
        }
    },

    saveEnabledMyMaps: function () {
        var myMapsList = [];
        this.myMapsList.forEach(function (map) {
            if (map.enabled) {
                myMapsList.push(map.id);
            }
        })

        var myMapsStringList = myMapsList.join('|');
        this.optionsController.saveOptionValues({enabledMyMaps: myMapsStringList});
        // this is used when tracks are loaded again
        this.optionsController.enabledMyMaps = myMapsList;
    },

    showAllMyMaps: function () {
        var that = this;
        if (!this.myMapsEnabled) {
            this.toggleMyMaps();
        }
        this.myMapsList.forEach(function (map) {
            if (!map.enabled) {
                that.toggleMyMap(map);
            }
        });
    },

    hideAllMyMaps: function () {
        var that = this;
        this.myMapsList.forEach(function (map) {
            if (map.enabled) {
                that.toggleMyMap(map);
            }
        });
    },

    addMenuEntry: function (map) {
        var name = map.name;
        var path = map.path;
        var color = map.color;

        // side menu entry
        var imgurl = generateUrl('/svg/core/actions/timezone?color=' + color.replace('#', ''));
        var li = '<li class="my-maps-item" id="' + name + '" map="' + map.id + '" name="' + name + '">' +
            '    <a href="#" class="my-maps-name" id="' + name + '-my-maps-name" title="' + escapeHTML(path) + '" style="background-image: url(' + imgurl + ')">' + name + '</a>' +
            '    <div class="app-navigation-entry-utils">' +
            '        <ul>' +
            '            <li class="app-navigation-entry-utils-menu-button myMapMenuButton">' +
            '                <button></button>' +
            '            </li>' +
            '        </ul>' +
            '    </div>' +
            '    <div class="app-navigation-entry-menu">' +
            '        <ul>' +
            '            <li>' +
            '                <a href="#" class="renameMyMap">' +
            '                    <span class="icon-rename"></span>' +
            '                    <span>' + t('maps', 'rename') + '</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="zoomMyMapButton">' +
            '                    <span class="icon-search"></span>' +
            '                    <span>' + t('maps', 'Zoom to bounds') + '</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="shareMyMap">' +
            '                    <span class="icon-share"></span>' +
            '                    <span>' + t('maps', 'Share') + '</span>' +
            '                </a>' +
            '            </li>' +
            '        </ul>' +
            '    </div>' +
            '</li>';

        var beforeThis = null;
        var that = this;

        var nameLower = name.toLowerCase();
        var myMapName;
        $('#my-maps-list > li').each(function () {
            myMapName = $(this).attr('name');
            if (nameLower.localeCompare(myMapName) < 0) {
                beforeThis = $(this);
                return false;
            }
        });
        if (beforeThis !== null) {
            $(li).insertBefore(beforeThis);
        } else {
            $('#my-maps-list').append(li);
        }
    },

    getMyMaps: function () {
        var that = this;
        $('#navigation-my-maps').addClass('icon-loading-small');
        /*var req = {};
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
            $('#navigation-my-maps').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load tracks'));
        });*/
        setTimeout(function () {
            $('#navigation-my-maps').removeClass('icon-loading-small');
            that.myMapsList.forEach(function (map) {
                that.addMenuEntry(map)
            })
            that.myMapsListLoaded = true;
        }, 500)
    },

    toggleMyMap: function (map) {
        if (!map.loaded) {
            OC.Notification.showTemporary("loaded map" + map.name);
            map.loaded = true;
        }
        var myMapItem = $('#my-maps-list > li[map="' + str(map.id) + '"]').find('.my-maps-name');
        if (map.enabled) {
            myMapItem.removeClass('active');
            $('#map').focus();
            OC.Notification.showTemporary("disabled map" + map.name);
            map.enabled = false;
        } else {
            myMapItem.addClass('active');
            OC.Notification.showTemporary("enabled map" + map.name);
            map.enabled = true;
        }
    },

    /*toggleMapTrackLayer: function(id, zoom=false) {
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
    },*/

    /*   loadTrack: function(id, save=false, pageLoad=false, zoom=false) {
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

       zoomOnMyMap: function(id) {
           OC.Notification.showTemporary("zoomed To Map"+str(id));
       },
       */

}

export default MyMapsController;
