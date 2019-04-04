function PhotosController (optionsController, timeFilterController) {
    this.PHOTO_MARKER_VIEW_SIZE = 40;
    this.photosDataLoaded = false;
    this.photosRequestInProgress = false;
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;
    this.photoMarkers = [];
    this.photoMarkersOldest = null;
    this.photoMarkersNewest = null;
    this.photoMarkersFirstVisible = 0;
    this.photoMarkersLastVisible = 0;
    this.timeFilterBegin = 0;
    this.timeFilterEnd = Date.now();
}

PhotosController.prototype = {

    initLayer : function(map) {
        this.map = map;
        var that = this;
        this.photoLayer = L.markerClusterGroup({
            iconCreateFunction : this.getClusterIconCreateFunction(),
            showCoverageOnHover : false,
            zoomToBoundsOnClick: false,
            maxClusterRadius: this.PHOTO_MARKER_VIEW_SIZE + 10,
            icon: {
                iconSize: [this.PHOTO_MARKER_VIEW_SIZE, this.PHOTO_MARKER_VIEW_SIZE]
            }
        });
        this.photoLayer.on('click', this.getPhotoMarkerOnClickFunction());
        this.photoLayer.on('clusterclick', function (a) {
            if (a.layer.getChildCount() > 30) {
                a.layer.zoomToBounds();
            }
            else {
                a.layer.spiderfy();
            }
        });
        // click on photo menu entry
        $('body').on('click', '#togglePhotosButton, #navigation-photos > a', function(e) {
            that.toggleLayer();
            that.optionsController.saveOptionValues({photosLayer: that.map.hasLayer(that.photoLayer)});
        });
        // click on menu button
        $('body').on('click', '.photosMenuButton', function(e) {
            var wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });
    },

    showLayer: function() {
        if (!this.photosDataLoaded && !this.photosRequestInProgress) {
            this.callForImages();
        }
        if (!this.map.hasLayer(this.photoLayer)) {
            this.map.addLayer(this.photoLayer);
        }
    },

    hideLayer: function() {
        if (this.map.hasLayer(this.photoLayer)) {
            this.map.removeLayer(this.photoLayer);
        }
    },

    toggleLayer: function() {
        if (this.map.hasLayer(this.photoLayer)) {
            this.hideLayer();
            // color of the eye
            $('#togglePhotosButton button').addClass('icon-toggle').attr('style', '');
        } else {
            this.showLayer();
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            $('#togglePhotosButton button').removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
        }
    },

    getPhotoMarkerOnClickFunction: function() {
        var _app = this;
        return function(evt) {
            var marker = evt.layer;
            var galleryUrl = OC.generateUrl('/apps/gallery/#'+encodeURIComponent(marker.data.path.replace(/^\//, '')));
            var win = window.open(galleryUrl, '_blank');
            if (win) {
                win.focus();
            }
        };
    },

    //getPhotoMarkerOnClickFunction() {
    //    var _app = this;
    //    return function(evt) {
    //        var marker = evt.layer;
    //        var content;
    //        if (marker.data.hasPreview) {
    //            var previewUrl = _app.generatePreviewUrl(marker.data.path);
    //            var img = '<img src=' + previewUrl + '/>';
    //            //Workaround for https://github.com/Leaflet/Leaflet/issues/5484
    //            $(img).on('load', function() {
    //                marker.getPopup().update();
    //            });
    //            content = img;
    //        } else {
    //            content = marker.data.path;
    //        }
    //        marker.bindPopup(content, {
    //            className: 'leaflet-popup-photo',
    //            maxWidth: 'auto'
    //        }).openPopup();
    //    }
    //},

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
                className: 'leaflet-marker-photo cluster-marker',
                html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + label + '</span>'
            }, this.icon));
        };
    },

    createPhotoView: function(markerData) {
        var iconUrl;
        if (markerData.hasPreview) {
            iconUrl = this.generatePreviewUrl(markerData.path);
        } else {
            iconUrl = this.getImageIconUrl();
        }
        //this.generatePreviewUrl(markerData.path);
        return L.divIcon(L.extend({
            html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​',
            className: 'leaflet-marker-photo photo-marker'
        }, markerData, {
            iconSize: [this.PHOTO_MARKER_VIEW_SIZE, this.PHOTO_MARKER_VIEW_SIZE],
            iconAnchor:   [this.PHOTO_MARKER_VIEW_SIZE / 2, this.PHOTO_MARKER_VIEW_SIZE]
        }));
    },

    addPhotosToMap : function(photos) {
        var markers = this.preparePhotoMarkers(photos);
        this.photoMarkers.push.apply(this.photoMarkers, markers);
        this.photoMarkers.sort(function (a, b) { return a.data.date - b.data.date;});
        this.refreshTimeFilter();
    },

    preparePhotoMarkers : function(photos) {
        var markers = [];
        for (var i = 0; i < photos.length; i++) {
            var markerData = {
                lat: photos[i].lat,
                lng: photos[i].lng,
                path: photos[i].path,
                albumId: photos[i].folderId,
                hasPreview : photos[i].hasPreview,
                date: photos[i].dateTaken
            };
            var marker = L.marker(markerData, {
                icon: this.createPhotoView(markerData)
            });
            marker.data = markerData;
            var previewUrl = this.generatePreviewUrl(marker.data.path);
            var date = new Date(photos[i].dateTaken*1000);
            var img = '<img src=' + previewUrl + '/>' +
                '<p class="tooltip-photo-name">' + escapeHTML(basename(markerData.path)) + '</p>' +
                '<p class="tooltip-photo-name">' + date.toIsoString() + '</p>';
            marker.bindTooltip(img, {permanent: false, className: "leaflet-marker-photo-tooltip"});
            markers.push(marker);
        }
        return markers;
    },

    refreshTimeFilter: function() {
        this.photoMarkersNewest = this.photoMarkers[this.photoMarkers.length - 1].data.date;
        this.photoMarkersOldest = this.photoMarkers[0].data.date;
        this.timeFilterController.updateSliderRangeFromController();
        this.timeFilterController.setSliderToMaxInterval();
        var hide = [];
        var show = [];
        var visible = false;
        for (var i = 0; i < this.photoMarkers.length; i++) {
            if (this.photoMarkers[i].data.date < this.timeFilterBegin) {
                hide.push(this.photoMarkers[i]);
            }
            else if (this.photoMarkers[i].data.date < this.timeFilterEnd) {
                show.push(this.photoMarkers[i]);
                if (!visible) {
                    this.photoMarkersFirstVisible = i;
                    visible = true;
                }
            }
            else {
                hide.push(this.photoMarkers[i]);
                if (visible) {
                    this.photoMarkersLastVisible = i-1;
                    visible = false;
                }
            }
        }
        if (visible) {
            this.photoMarkersLastVisible = i - 1;
            visible = false;
        }
        //this.photoLayer.clearLayers();
        this.photoLayer.addLayers(show);

    },

    updateTimeFilterBegin: function (date) {
        if (this.photoMarkers.length === 0) {
            return;
        }
        if (date <= this.timeFilterEnd) {
            var i = this.photoMarkersFirstVisible;
            if (date < this.timeFilterBegin) {
                i = i-1;
                while (i >= 0 && i <= this.photoMarkersLastVisible && this.photoMarkers[i].data.date > date) {
                    this.photoLayer.addLayer(this.photoMarkers[i]);
                    i = i-1;
                }
                this.photoMarkersFirstVisible = i + 1;
            } else {
                while (i >= 0 && i <= this.photoMarkersLastVisible && this.photoMarkers[i].data.date < date) {
                    this.photoLayer.removeLayer(this.photoMarkers[i]);
                    i = i + 1;
                }
                this.photoMarkersFirstVisible = i;
            }
            this.timeFilterBegin = date;
        } else {
            this.updateTimeFilterBegin(this.timeFilterEnd);
        }
    },

    updateTimeFilterEnd: function (date){
        if (this.photoMarkers.length === 0) {
            return;
        }
        if (date >= this.timeFilterBegin) {
            var i = this.photoMarkersLastVisible;
            if (date < this.timeFilterEnd) {
                while (i >= this.photoMarkersFirstVisible && i < this.photoMarkers.length && this.photoMarkers[i].data.date > date ) {
                    this.photoLayer.removeLayer(this.photoMarkers[i]);
                    i = i-1;
                }
                this.photoMarkersLastVisible = i;
            } else {
                i = i+1;
                while (i >= this.photoMarkersFirstVisible && i < this.photoMarkers.length && this.photoMarkers[i].data.date < date) {
                    this.photoLayer.addLayer(this.photoMarkers[i]);
                    i = i+1;
                }
                this.photoMarkersLastVisible = i - 1;
            }
            this.timeFilterEnd = date;
        } else {
            this.updateTimeFilterEnd(this.timeFilterBegin);
        }
    },

    callForImages: function() {
        this.photosRequestInProgress = true;
        $.ajax({
            'url' : OC.generateUrl('apps/maps/photos'),
            'type': 'GET',
            'context' : this,
            'success': function(response) {
                if (response.length == 0) {
                    //showNoPhotosMessage();
                } else {
                    this.addPhotosToMap(response);
                }
                this.photosDataLoaded = true;
            },
            'complete': function(response) {
                this.photosRequestInProgress = false;
            }
        });
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
    }

};

