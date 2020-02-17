import { generateUrl } from '@nextcloud/router';

function NonLocalizedPhotosController (optionsController, timeFilterController, photosController) {
    this.PHOTO_MARKER_VIEW_SIZE = 40;
    this.nonLocalizedPhotosDataLoaded = false;
    this.lat = 0;
    this.lng = 0;
    this.nonLocalizedPhotosRequestInProgress = false;
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;
    this.photosController = photosController;
    this.nonLocalizedPhotoMarkers = [];
    this.nonLocalizedPhotoMarkersOldest = null;
    this.nonLocalizedPhotoMarkersNewest = null;
    this.nonLocalizedPhotoMarkersFirstVisible = 0;
    this.nonLocalizedPhotoMarkersLastVisible = -1;
    this.timeFilterBegin = 0;
    this.timeFilterEnd = Date.now();
}

NonLocalizedPhotosController.prototype = {

    initLayer : function(map) {
        this.map = map;
        var that = this;
        this.nonLocalizedPhotoLayer = L.markerClusterGroup({
            iconCreateFunction : this.getClusterIconCreateFunction(),
            spiderfyOnMaxZoom: false,
            showCoverageOnHover : false,
            zoomToBoundsOnClick: false,
            maxClusterRadius: this.PHOTO_MARKER_VIEW_SIZE + 10,
            icon: {
                iconSize: [this.PHOTO_MARKER_VIEW_SIZE, this.PHOTO_MARKER_VIEW_SIZE]
            }
        });
        this.nonLocalizedPhotoLayer.on('click', this.getNonLocalizedPhotoMarkerOnClickFunction());
        this.nonLocalizedPhotoLayer.on('clusterclick', function (a) {
            if (a.layer.getChildCount() > 20 && that.map.getZoom() !== that.map.getMaxZoom()) {
                a.layer.zoomToBounds();
            }
            else {
                a.layer.spiderfy();
            }
        });
        // menu entry icon
        var picimgurl = generateUrl('/svg/core/places/picture?color=eca700');
        $('#navigation-nonLocalizedPhotos > a.icon-picture').attr('style', 'background-image: url('+picimgurl+');')
        // click on nonLocalizedPhoto menu entry
        $('body').on('click', '#navigation-nonLocalizedPhotos > a', function(e) {
            that.toggleLayer();
            that.optionsController.saveOptionValues({nonLocalizedPhotosLayer: that.map.hasLayer(that.nonLocalizedPhotoLayer)});
            that.updateTimeFilterRange();
            that.timeFilterController.setSliderToMaxInterval();
        });

        //save geolocations to pictures
        $('body').on('click', '.save-all-nonlocalized', function(e) {
            that.menuSaveAllVisible();
        });

        if (navigator.geolocation && window.isSecureContext) {
            navigator.geolocation.getCurrentPosition(function (position) {
                that.lat = position.coords.latitude;
                that.lng = position.coords.longitude;
            });
        }
        else {
            this.lat = 0;
            this.lng = 0;
            OC.Notification.showTemporary(t('maps', 'Impossible to get current location'));
        }
    },

    updateMyFirstLastDates: function() {
        var nbMarkers = this.nonLocalizedPhotoMarkers.length;
        var layerVisible = this.map.hasLayer(this.nonLocalizedPhotoLayer);
        this.nonLocalizedPhotoMarkersOldest = (layerVisible && nbMarkers > 0) ? this.nonLocalizedPhotoMarkers[0].data.date : null;
        this.nonLocalizedPhotoMarkersNewest = (layerVisible && nbMarkers > 0) ? this.nonLocalizedPhotoMarkers[nbMarkers - 1].data.date : null;
    },

    showLayer: function() {
        if (!this.nonLocalizedPhotosDataLoaded && !this.nonLocalizedPhotosRequestInProgress) {
            this.callForImages();
        }
        if (!this.map.hasLayer(this.nonLocalizedPhotoLayer)) {
            this.map.addLayer(this.nonLocalizedPhotoLayer);
        }
    },

    hideLayer: function() {
        if (this.map.hasLayer(this.nonLocalizedPhotoLayer)) {
            this.map.removeLayer(this.nonLocalizedPhotoLayer);
        }
    },

    toggleLayer: function() {
        if (this.map.hasLayer(this.nonLocalizedPhotoLayer)) {
            this.hideLayer();
            $('#navigation-nonLocalizedPhotos').removeClass('active');
            $('#map').focus();
        } else {
            this.showLayer();
            $('#navigation-nonLocalizedPhotos').addClass('active');
        }
    },

    getNonLocalizedPhotoMarkerOnClickFunction: function() {
        var _app = this;
        return function(evt) {
            return null;
        };
    },


    getClusterIconCreateFunction: function() {
        var _app = this;
        return function(cluster) {
            var marker = cluster.getAllChildMarkers()[0].data;
            var iconUrl;
            if (marker.hasPreview) {
                iconUrl = _app.generatePreviewUrl(marker.fileId);
            } else {
                iconUrl = _app.getImageIconUrl();
            }
            var label = cluster.getChildCount();
            return new L.DivIcon(L.extend({
                className: 'leaflet-marker-nonLocalizedPhoto cluster-marker',
                html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + label + '</span>'
            }, this.icon));
        };
    },

    createNonLocalizedPhotoView: function(markerData) {
        var iconUrl;
        if (markerData.hasPreview) {
            iconUrl = this.generatePreviewUrl(markerData.fileId);
        } else {
            iconUrl = this.getImageIconUrl();
        }
        return L.divIcon(L.extend({
            html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​',
            className: 'leaflet-marker-nonLocalizedPhoto nonLocalizedPhoto-marker'
        }, markerData, {
            iconSize: [this.PHOTO_MARKER_VIEW_SIZE, this.PHOTO_MARKER_VIEW_SIZE],
            iconAnchor:   [this.PHOTO_MARKER_VIEW_SIZE / 2, this.PHOTO_MARKER_VIEW_SIZE]
        }));
    },

    addNonLocalizedPhotosToMap : function(nonLocalizedPhotos) {
        var markers = this.prepareNonLocalizedPhotoMarkers(nonLocalizedPhotos);
        this.nonLocalizedPhotoMarkers.push.apply(this.nonLocalizedPhotoMarkers, markers);
        this.nonLocalizedPhotoMarkers.sort(function (a, b) { return a.data.date - b.data.date;});

        // we put them all in the layer
        this.nonLocalizedPhotoMarkersFirstVisible = 0;
        this.nonLocalizedPhotoMarkersLastVisible = this.nonLocalizedPhotoMarkers.length - 1;
        this.nonLocalizedPhotoLayer.addLayers(this.nonLocalizedPhotoMarkers);

        this.updateTimeFilterRange();
        this.timeFilterController.setSliderToMaxInterval();
    },

    prepareNonLocalizedPhotoMarkers : function(nonLocalizedPhotos) {
        var that = this;
        var markers = [];
        for (var i = 0; i < nonLocalizedPhotos.length; i++) {
            var markerData = {
                lat: nonLocalizedPhotos[i].lat || this.lat,
                lng: nonLocalizedPhotos[i].lng || this.lng,
                path: nonLocalizedPhotos[i].path,
                albumId: nonLocalizedPhotos[i].folderId,
                fileId: nonLocalizedPhotos[i].fileId,
                hasPreview : nonLocalizedPhotos[i].hasPreview,
                date: nonLocalizedPhotos[i].dateTaken
            };
            var marker = L.marker(markerData, {
                draggable: true,
                icon: this.createNonLocalizedPhotoView(markerData)
            });
            marker.data = markerData;
            var previewUrl = this.generatePreviewUrl(marker.data.fileId);
            var dateStr = OC.Util.formatDate(nonLocalizedPhotos[i].dateTaken*1000);
            var img = '<img class="photo-tooltip" src=' + previewUrl + '/>' +
                '<p class="tooltip-photo-date">' + dateStr + '</p>' +
                '<p class="tooltip-photo-name">' + escapeHTML(basename(markerData.path)) + '</p>';
            marker.bindTooltip(img, {permanent: false, className: "leaflet-marker-photo-tooltip"});
            marker.on('dragstart', function (e) {
                e.target.closeTooltip();
                e.target.unbindTooltip();
                e.target.lastdrag = Date.now();
                e.target.lastdragactive = true;
            });
            marker.on('dragend', function (e) {
                e.target.lastdragactive = false;
                setTimeout(function () {
                    var diff = Date.now() - e.target.lastdrag;
                    if ( diff  > 2000 && !e.target.lastdragactive) {
                        that.saveCordinatesToImage(e.target);
                    }
                }, 2001);
            });

            marker.on('click', function (e) {
                that.saveCordinatesToImage(e.target);
            });

            markers.push(marker);
        }
        return markers;
    },

    updateTimeFilterRange: function() {
        this.updateMyFirstLastDates();
        this.timeFilterController.updateSliderRangeFromController();
    },

    updateTimeFilterBegin: function (date) {
        if (date <= this.timeFilterEnd) {
            var i = this.nonLocalizedPhotoMarkersFirstVisible;
            if (date < this.timeFilterBegin) {
                i = i-1;
                while (i >= 0 && i <= this.nonLocalizedPhotoMarkersLastVisible && this.nonLocalizedPhotoMarkers[i].data.date >= date) {
                    this.nonLocalizedPhotoLayer.addLayer(this.nonLocalizedPhotoMarkers[i]);
                    i = i-1;
                }
                this.nonLocalizedPhotoMarkersFirstVisible = i + 1;
            }
            else {
                while (i < this.nonLocalizedPhotoMarkers.length && i >= 0 && i <= this.nonLocalizedPhotoMarkersLastVisible && this.nonLocalizedPhotoMarkers[i].data.date < date) {
                    this.nonLocalizedPhotoLayer.removeLayer(this.nonLocalizedPhotoMarkers[i]);
                    i = i + 1;
                }
                this.nonLocalizedPhotoMarkersFirstVisible = i;
            }
            this.timeFilterBegin = date;
        }
        else {
            this.updateTimeFilterBegin(this.timeFilterEnd);
        }
    },

    updateTimeFilterEnd: function (date){
        if (date >= this.timeFilterBegin) {
            var i = this.nonLocalizedPhotoMarkersLastVisible;
            if (date < this.timeFilterEnd) {
                while (i >= 0 && i >= this.nonLocalizedPhotoMarkersFirstVisible && this.nonLocalizedPhotoMarkers[i].data.date > date ) {
                    this.nonLocalizedPhotoLayer.removeLayer(this.nonLocalizedPhotoMarkers[i]);
                    i = i-1;
                }
                this.nonLocalizedPhotoMarkersLastVisible = i;
            }
            else {
                i = i+1;
                while (i >= this.nonLocalizedPhotoMarkersFirstVisible && i < this.nonLocalizedPhotoMarkers.length && this.nonLocalizedPhotoMarkers[i].data.date <= date) {
                    this.nonLocalizedPhotoLayer.addLayer(this.nonLocalizedPhotoMarkers[i]);
                    i = i+1;
                }
                this.nonLocalizedPhotoMarkersLastVisible = i - 1;
            }
            this.timeFilterEnd = date;
        }
        else {
            this.updateTimeFilterEnd(this.timeFilterBegin);
        }
    },

    callForImages: function() {
        this.nonLocalizedPhotosRequestInProgress = true;
        $('#navigation-nonLocalizedPhotos').addClass('icon-loading-small');
        $.ajax({
            url: generateUrl('apps/maps/photos/nonlocalized'),
            type: 'GET',
            async: true,
            context: this
        }).done(function (response) {
            if (response.length == 0) {
                //showNoPhotosMessage();
            }
            else {
                this.addNonLocalizedPhotosToMap(response);
            }
            this.nonLocalizedPhotosDataLoaded = true;
        }).always(function (response) {
            this.nonLocalizedPhotosRequestInProgress = false;
            $('#navigation-nonLocalizedPhotos').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load non-geolocalized photos'));
        });
    },

    saveCordinatesToImage : function (marker) {
        var latlng = marker.getLatLng();
        this.photosController.placePhotos([marker.data.path], [latlng.lat], [latlng.lng]);
        var date = marker.data.date;
        var path = marker.data.path;
        var removedMarkers = [];
        for (var i = this.nonLocalizedPhotoMarkersFirstVisible; i < this.nonLocalizedPhotoMarkers.length && this.nonLocalizedPhotoMarkers[i].data.date <= date; i++) {
            if (this.nonLocalizedPhotoMarkers[i].data.path === path) {
                var j = i + 1;
                while (j < this.nonLocalizedPhotoMarkers.length && this.nonLocalizedPhotoMarkers[j].data.path === path) {
                    j++;
                }
                removedMarkers.push(...this.nonLocalizedPhotoMarkers.splice(i, j-i));
                i--;
            }
        }
        var that = this;
        removedMarkers.forEach(function (m) {
            that.nonLocalizedPhotoLayer.removeLayer(m);
        });
        this.nonLocalizedPhotoMarkersLastVisible = this.nonLocalizedPhotoMarkersLastVisible - removedMarkers.length;
    },

    /* Preview size 32x32 is used in files view, so it sould be generated */
    generateThumbnailUrl: function (filename) {
        return generateUrl('core') + '/preview.png?file=' + encodeURI(filename) + '&x=32&y=32';
    },

    /* Preview size 341x256 is commonly found in preview folder */
    generatePreviewUrl: function (fileId) {
        return generateUrl('core') + '/preview?fileId=' + fileId + '&x=341&y=256&a=1';
    },

    getImageIconUrl: function() {
        return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2';
    },

    menuSaveAllVisible: function(e) {
        var paths = [];
        var lats = [];
        var lngs = [];
        for (var i=this.nonLocalizedPhotoMarkersFirstVisible; i<=this.nonLocalizedPhotoMarkersLastVisible; i++) {
            var markerData = this.nonLocalizedPhotoMarkers[i].data;
            paths.push( markerData.path);
            lats.push(markerData.lat);
            lngs.push(markerData.lng);
            this.nonLocalizedPhotoLayer.removeLayer(this.nonLocalizedPhotoMarkers[i]);
            delete this.nonLocalizedPhotoMarkers[i];
        }
        this.photosController.placePhotos(paths, lats, lngs);
        this.nonLocalizedPhotoMarkers.splice(this.nonLocalizedPhotoMarkersFirstVisible, this.nonLocalizedPhotoMarkersLastVisible - this.nonLocalizedPhotoMarkersFirstVisible + 1);
        this.nonLocalizedPhotoMarkersLastVisible = this.nonLocalizedPhotoMarkersFirstVisible -1;
    },
};

export default NonLocalizedPhotosController;
