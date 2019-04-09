function NonLocalizedPhotosController (optionsController, timeFilterController) {
    this.PHOTO_MARKER_VIEW_SIZE = 40;
    this.nonLocalizedPhotosDataLoaded = false;
    this.lat = 0;
    this.lng = 0;
    this.nonLocalizedPhotosRequestInProgress = false;
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;
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
            showCoverageOnHover : false,
            zoomToBoundsOnClick: false,
            maxClusterRadius: this.PHOTO_MARKER_VIEW_SIZE + 10,
            icon: {
                iconSize: [this.PHOTO_MARKER_VIEW_SIZE, this.PHOTO_MARKER_VIEW_SIZE]
            }
        });
        this.nonLocalizedPhotoLayer.on('click', this.getNonLocalizedPhotoMarkerOnClickFunction());
        this.nonLocalizedPhotoLayer.on('clusterclick', function (a) {
            if (a.layer.getChildCount() > 20) {
                a.layer.zoomToBounds();
            }
            else {
                a.layer.spiderfy();
            }
        });
        // click on nonLocalizedPhoto menu entry
        $('body').on('click', '#toggleNonLocalizedPhotosButton, #navigation-nonLocalizedPhotos > a', function(e) {
            that.toggleLayer();
            that.optionsController.saveOptionValues({nonLocalizedPhotosLayer: that.map.hasLayer(that.nonLocalizedPhotoLayer)});
        });
        // click on menu button
        $('body').on('click', '.nonLocalizedPhotosMenuButton', function(e) {
            var wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {
                that.lat = position.coords.latitude;
                that.lng = position.coords.longitude;
            });
        }
    },

    updateMyFirstLastDates: function() {
        var firstVisible = this.nonLocalizedPhotoMarkersFirstVisible;
        var lastVisible = this.nonLocalizedPhotoMarkersLastVisible;
        var layerVisible = this.map.hasLayer(this.nonLocalizedPhotoLayer);
        this.nonLocalizedPhotoMarkersOldest = layerVisible ? this.nonLocalizedPhotoMarkers[firstVisible].data.date : null;
        this.nonLocalizedPhotoMarkersNewest = layerVisible ? this.nonLocalizedPhotoMarkers[lastVisible].data.date : null;
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
            // color of the eye
            $('#toggleNonLocalizedPhotosButton button').addClass('icon-toggle').attr('style', '');
        } else {
            this.showLayer();
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            $('#toggleNonLocalizedPhotosButton button').removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
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
                iconUrl = _app.generatePreviewUrl(marker.path);
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
            iconUrl = this.generatePreviewUrl(markerData.path);
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
                hasPreview : nonLocalizedPhotos[i].hasPreview,
                date: nonLocalizedPhotos[i].dateTaken
            };
            var marker = L.marker(markerData, {
                draggable: true,
                icon: this.createNonLocalizedPhotoView(markerData)
            });
            marker.data = markerData;
            var previewUrl = this.generatePreviewUrl(marker.data.path);
            var date = new Date(nonLocalizedPhotos[i].dateTaken*1000);
            var img = '<img src=' + previewUrl + '/>' +
                '<p class="tooltip-nonLocalizedPhoto-name">' + escapeHTML(basename(markerData.path)) + '</p>' +
                '<p class="tooltip-nonLocalizedPhoto-name">' + date.toIsoString() + '</p>';
            marker.bindTooltip(img, {permanent: false, className: "leaflet-marker-nonLocalizedPhoto-tooltip"});
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
        $.ajax({
            'url' : OC.generateUrl('apps/maps/photos/nonlocalized'),
            'type': 'GET',
            'context' : this,
            'success': function(response) {
                if (response.length == 0) {
                    //showNoPhotosMessage();
                } else {
                    this.addNonLocalizedPhotosToMap(response);
                }
                this.nonLocalizedPhotosDataLoaded = true;
            },
            'complete': function(response) {
                this.nonLocalizedPhotosRequestInProgress = false;
            }
        });
    },

    saveCordinatesToImage : function (marker) {
        console.log(marker.getLatLng());
    },

    /* Preview size 32x32 is used in files view, so it sould be generated */
    generateThumbnailUrl: function (filename) {
        return OC.generateUrl('core') + '/preview.png?file=' + encodeURI(filename) + '&x=32&y=32';
    },

    /* Preview size 375x211 is used in files details view */
    generatePreviewUrl: function (filename) {
        return OC.generateUrl('core') + '/preview.png?file=' + encodeURI(filename) + '&x=375&y=211&a=1';
    },

    getImageIconUrl: function() {
        return OC.generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2';
    },
};

