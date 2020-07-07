import {generateUrl} from '@nextcloud/router';

import {basename} from './utils';

import escapeHTML from 'escape-html';

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
    this.photoMarkersLastVisible = -1;
    this.timeFilterBegin = 0;
    this.timeFilterEnd = Date.now();

    this.movingPhotoPath = null;
    this.isPhotosInstalled = OCP.InitialState.loadState('maps', 'photos');
}

PhotosController.prototype = {

    initLayer: function (map) {
        this.map = map;
        var that = this;
        this.photoLayer = L.markerClusterGroup({
            iconCreateFunction: this.getClusterIconCreateFunction(),
            spiderfyOnMaxZoom: false,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: false,
            maxClusterRadius: this.PHOTO_MARKER_VIEW_SIZE + 10,
            icon: {
                iconSize: [this.PHOTO_MARKER_VIEW_SIZE, this.PHOTO_MARKER_VIEW_SIZE]
            }
        });
        this.photoLayer.on('click', this.getPhotoMarkerOnClickFunction());
        this.photoLayer.on('clusterclick', function (a) {
            if (a.layer.getChildCount() > 20 && that.map.getZoom() !== that.map.getMaxZoom()) {
                a.layer.zoomToBounds();
            } else {
                if (OCA.Viewer && OCA.Viewer.open) {
                    var photolist = a.layer.getAllChildMarkers().map(function (m) {
                        return m.data;
                    });
                    photolist.sort(function (a, b) {
                        return a.dateTaken - b.dateTaken;
                    });
                    OCA.Viewer.open({path: photolist[0].path, list: photolist});
                } else {
                    a.layer.spiderfy();
                    that.map.clickpopup = true;
                }
            }
        });
        //right click on photo cluster:
        this.photoLayer.on('clustercontextmenu', this.photoClusterContextmenu);
        // click on photo menu entry
        $('body').on('click', '#navigation-photos > a', function (e) {
            that.toggleLayer();
            that.optionsController.saveOptionValues({photosLayer: that.map.hasLayer(that.photoLayer)});
            that.updateTimeFilterRange();
            that.timeFilterController.setSliderToMaxInterval();
        });
        $('body').on('click', '.movephoto', function (e) {
            var ul = $(this).parent().parent();
            var filePath = ul.attr('filepath');
            that.movingPhotoPath = filePath;
            that.enterMovePhotoMode();
            that.map.closePopup();
        });
        $('body').on('click', '.resetphoto', function (e) {
            var ul = $(this).parent().parent();
            var filePath = ul.attr('filepath');
            that.resetPhotoCoords([filePath]);
            that.map.closePopup();
        });
        // expand navigation
        $('body').on('click', '#navigation-photos', function (e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-photos') {
                that.toggleNavigation();
                that.optionsController.saveOptionValues({photosNavigationShow: $('#navigation-favorites').hasClass('open')});
            }
        });
        // PhotoClusterMouseRightClick zoom-in
        $('body').on('click', '.zoomin-photo-cluster-right-click', function (e) {
            var ul = $(this).parent().parent();
            var thisClusterLayerId = ul.attr('layerId');
            if (that.map.getZoom() !== that.map.getMaxZoom()) {
                that.map._layers[thisClusterLayerId].zoomToBounds();
            }
            that.map.closePopup();
        });
        $('body').on('click', '.viewphotos', function (e) {
            var ul = $(this).parent().parent();
            var thisClusterLayerId = ul.attr('layerId');
            if (OCA.Viewer && OCA.Viewer.open) {
                var photolist = that.map._layers[thisClusterLayerId].getAllChildMarkers().map(function (m) {
                    return m.data;
                });
                photolist.sort(function (a, b) {
                    return a.dateTaken - b.dateTaken;
                });
                OCA.Viewer.open({path: photolist[0].path, list: photolist});
            } else {
                that.map._layers[thisClusterLayerId].spiderfy();
                that.map.clickpopup = true;
            }
            that.map.closePopup();
        });
    },

    updateMyFirstLastDates: function () {
        var nbMarkers = this.photoMarkers.length;
        var layerVisible = this.map.hasLayer(this.photoLayer);
        this.photoMarkersOldest = (layerVisible && nbMarkers > 0) ? this.photoMarkers[0].data.dateTaken : null;
        this.photoMarkersNewest = (layerVisible && nbMarkers > 0) ? this.photoMarkers[nbMarkers - 1].data.dateTaken : null;
    },

    showLayer: function () {
        if (!this.photosDataLoaded && !this.photosRequestInProgress) {
            this.callForImages();
        }
        if (!this.map.hasLayer(this.photoLayer)) {
            this.map.addLayer(this.photoLayer);
        }
    },

    hideLayer: function () {
        if (this.map.hasLayer(this.photoLayer)) {
            this.map.removeLayer(this.photoLayer);
        }
    },

    toggleLayer: function () {
        if (this.map.hasLayer(this.photoLayer)) {
            this.hideLayer();
            $('#navigation-photos').removeClass('active');
            $('#map').focus();
        } else {
            this.showLayer();
            $('#navigation-photos').addClass('active');
        }
    },

    toggleNavigation: function () {
        $('#navigation-photos').toggleClass('open');
    },

    getPhotoMarkerOnClickFunction: function () {
        var _app = this;
        return function (evt) {
            var marker = evt.layer;
            // use Viewer app if available and recent enough to provide standalone viewer
            if (OCA.Viewer && OCA.Viewer.open) {
                OCA.Viewer.open({path: marker.data.path, list: [marker.data]});
            } else {
                var galleryUrl;
                if (_app.isPhotosInstalled) {
                    var dir = OC.dirname(marker.data.path);
                    galleryUrl = generateUrl('/apps/photos/albums/' + dir.replace(/^\//, ''));
                } else {
                    galleryUrl = generateUrl('/apps/gallery/#' + encodeURIComponent(marker.data.path.replace(/^\//, '')));
                }
                var win = window.open(galleryUrl, '_blank');
                if (win) {
                    win.focus();
                }
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

    getClusterIconCreateFunction: function () {
        var _app = this;
        return function (cluster) {
            var availZoomLevels = cluster._map.getMaxZoom() - cluster._map.getZoom();
            var marker = cluster.getAllChildMarkers()[0].data;
            var iconUrl;
            if (marker.hasPreview) {
                iconUrl = _app.generatePreviewUrl(marker.fileId);
            } else {
                iconUrl = _app.getImageIconUrl();
            }
            var label = cluster.getChildCount();
            if (availZoomLevels === 0 && label > 1) {
                // lets generate a preview slideshow for cluster of images at max zoom level
                var iMarkerList = cluster.getAllChildMarkers();
                // sort by dateTaken
                iMarkerList.sort(function (a, b) {
                    return a.data.dateTaken - b.data.dateTaken;
                });
                var firstImageFileId = iMarkerList[0].data.fileId;
                var imgList = '<div imgindex="0" id="imgdiv">';
                for (var i = 0; i < cluster.getChildCount(); i++) {
                    var iMarker = iMarkerList[i].data;
                    var iIconUrl = _app.generatePreviewUrl(iMarker.fileId);
                    var iDateStr = OC.Util.formatDate(iMarker.dateTaken * 1000);
                    var iPhotoName = escapeHTML(basename(iMarker.path));
                    var img = '<div id="imgdiv' + i + '" style="display: none">' +
                        '<img class="photo-tooltip" src=' + iIconUrl + '/>' +
                        '<p class="tooltip-photo-date">' + iDateStr + '</p>' +
                        '<p class="tooltip-photo-name">' + iPhotoName + '</p>' +
                        '<p class="tooltip-photo-name">' + (parseInt(i) + 1) + ' of ' + label + '</p>' +
                        '</div>';
                    imgList += img;
                }
                imgList += '</div>'
                cluster.bindTooltip(imgList, {
                    permanent: false,
                    className: 'leaflet-marker-photo-tooltip',
                    direction: 'right',
                    offset: L.point(0, -150)
                });
                cluster.on("tooltipopen", function (event, ui) {
                    var maxI = parseInt(cluster.getChildCount()) - 1;
                    var imgIndexAttr = $('#imgdiv').parent().parent().attr('imgindex');
                    if (imgIndexAttr >= 0) {
                        // a preview image loop was already running before.
                        // need to check if again opening the same preview image loop as before to continue or if opening another preview image loop and start at first image. We identify this using the attribute firstImageFileId we stored in 2nd parent above #imgdiv
                        var lastFirstImageFileId = $('#imgdiv').parent().parent().attr('firstImageFileId');
                        if (lastFirstImageFileId == firstImageFileId) {
                            // we continue the loop that was already running before
                        } else {
                            // we start a new preview loop
                            imgIndexAttr = 0;
                            // we store imgindex of preview loop in attribute imgindex in 2nd parent above #imgdiv
                            $('#imgdiv').parent().parent().attr('imgindex', '0');
                        }
                    }
                    $('#imgdiv').parent().parent().attr('firstImageFileId', firstImageFileId);
                    $('#imgdiv').show();
                    // For some browsers, `attr` is undefined; for others,
                    // `attr` is false.  Check for both.
                    if (typeof imgIndexAttr == typeof undefined || imgIndexAttr == false) {
                        // imgindex not yet defined therefore this is the fist time tooltipopen running
                        // for this cluster. Therefore we start with imgindex 0
                        $('#imgdiv').parent().parent().attr('imgindex', '0'); // saving 0 as start value to attribute imgindex
                        $('#imgdiv0').show(); // showing first image
                    } else {
                        // this loop was previously visible therefore we continue with last visible image
                        $('#imgdiv' + imgIndexAttr).show();
                    }
                    var randomId = Math.random(); //
                    $('#imgdiv').parent().parent().attr('randomId', randomId);

                    function toolTipImgLoop (maxI, randomId) {
                        // will will only continue the loop if randomId is matching randomId stored in imgdiv
                        // to prevent running multiple loops in parallel could be caused by to fast mouseout / mouseover events
                        setTimeout(function (maxI) {  // this function content will be executed after timeout of 3 sec (3000 ms)
                            var randomIdFromImgdiv = $('#imgdiv').parent().parent().attr('randomId');
                            if (randomId == randomIdFromImgdiv) {
                                var i = $('#imgdiv').parent().parent().attr('imgindex');
                                var j = (parseInt(i) + 1);
                                if (i == maxI) { // if i reached max image j need to start with 0 again to continue with 1st image again
                                    j = 0;
                                }
                                // now we will fade out the current img and fade in the next image
                                $('#imgdiv' + i).fadeOut('fast', function () {
                                    $('#imgdiv' + j).fadeIn('fast');
                                });
                                if (i == maxI) { // and now we also need to switch i back to 0 to contine
                                    i = 0;
                                } else {
                                    i++;
                                }
                                $('#imgdiv').parent().parent().attr('imgindex', i);
                                // after storing current value of i (loop img index) in imgdiv imgindex attribute
                                // we will call again toolTipImgLoop to continue the loop
                                toolTipImgLoop(maxI, randomId);
                            }
                        }, 3000, maxI); // timeout and variable of above setTimeout
                    }
                    // initial call of toolTipImgLoop
                    // we will do the inital call of toolTipImgLoop using setTimeout with timeout 500 ms to ensure that tooltipopen has been completed
                    setTimeout(toolTipImgLoop(maxI, randomId), 500, maxI, randomId);
                });
                cluster.on("tooltipclose", function (event, ui) {
                    // clearing randomId in imgdiv on mouseout to stop the img preview loop (see above)
                    $('#imgdiv').parent().parent().attr('randomId', 0);
                });
            }
            return new L.DivIcon(L.extend({
                className: 'leaflet-marker-photo cluster-marker',
                html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + label + '</span>'
            }, this.icon));
        };
    },

    createPhotoView: function (markerData) {
        var iconUrl;
        if (markerData.hasPreview) {
            iconUrl = this.generatePreviewUrl(markerData.fileId);
        } else {
            iconUrl = this.getImageIconUrl();
        }
        return L.divIcon(L.extend({
            html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​',
            className: 'leaflet-marker-photo photo-marker'
        }, markerData, {
            iconSize: [this.PHOTO_MARKER_VIEW_SIZE, this.PHOTO_MARKER_VIEW_SIZE],
            iconAnchor: [this.PHOTO_MARKER_VIEW_SIZE / 2, this.PHOTO_MARKER_VIEW_SIZE]
        }));
    },

    addPhotosToMap: function (photos) {
        var markers = this.preparePhotoMarkers(photos);
        this.photoMarkers.push.apply(this.photoMarkers, markers);
        this.photoMarkers.sort(function (a, b) {
            return a.data.dateTaken - b.data.dateTaken;
        });

        // we update the counter
        var catCounter = $('#navigation-photos .app-navigation-entry-utils-counter');
        var photoCounter = this.photoMarkers.length;
        if (photoCounter >= 10000) {
            catCounter.attr('title', photoCounter + ' photos');
            photoCounter = photoCounter = Math.floor(photoCounter / 1000);
            catCounter.text(photoCounter + 'k');
        } else {
            catCounter.text(photoCounter);
        }

        // we put them all in the layer
        this.photoMarkersFirstVisible = 0;
        this.photoMarkersLastVisible = this.photoMarkers.length - 1;
        this.photoLayer.addLayers(this.photoMarkers);

        this.updateTimeFilterRange();
        this.timeFilterController.setSliderToMaxInterval();
    },

    preparePhotoMarkers: function (photos) {
        var markers = [];
        for (var i = 0; i < photos.length; i++) {
            var markerData = photos[i];
            var marker = L.marker(markerData, {
                icon: this.createPhotoView(markerData)
            });
            marker.data = markerData;
            var previewUrl = this.generatePreviewUrl(marker.data.fileId);
            var dateStr = OC.Util.formatDate(photos[i].dateTaken * 1000);
            var img = '<img class="photo-tooltip" src=' + previewUrl + '/>' +
                '<p class="tooltip-photo-date">' + dateStr + '</p>' +
                '<p class="tooltip-photo-name">' + escapeHTML(basename(markerData.path)) + '</p>';
            marker.bindTooltip(img, {
                permanent: false,
                className: 'leaflet-marker-photo-tooltip',
                direction: 'right',
                offset: L.point(0, -30)
            });
            marker.on('contextmenu', this.photoMouseRightClick);
            markers.push(marker);
        }
        return markers;
    },

    photoClusterContextmenu: function (a) {
        var layerId = a.layer._leaflet_id;
        a.layer.unbindPopup();
        var popupContent = this._map.photosController.getPhotoClusterContextmenuPopupContent(layerId);
        a.layer.bindPopup(popupContent, {
            closeOnClick: true,
            className: 'popovermenu open popupMarker',
            offset: L.point(-5, -20)
        });
        a.layer.openPopup();
    },

    getPhotoClusterContextmenuPopupContent: function (layerId) {
        var viewText = t('maps', 'Show batch in viewer');
        var zoomText = t('maps', 'Zoom in');
        var res =
            '<ul layerId="' + layerId + '">' +
            '   <li>' +
            '       <button class="icon-play viewphotos">' +
            '           <span>' + viewText + '</span>' +
            '       </button>' +
            '   </li>' +
            '   <li>' +
            '       <button class="icon-search zoomin-photo-cluster-right-click">' +
            '           <span>' + zoomText + '</span>' +
            '       </button>' +
            '   </li>' +
            '</ul>';
        return res;
    },

    photoMouseRightClick: function (e) {
        var that = this;
        var filePath = e.target.data.path;

        var popupContent = this._map.photosController.getPhotoContextPopupContent(filePath);
        this._map.clickpopup = true;

        var popup = L.popup({
            closeOnClick: true,
            className: 'popovermenu open popupMarker',
            offset: L.point(-5, -20)
        })
            .setLatLng(e.target._latlng)
            .setContent(popupContent)
            .openOn(this._map);
        $(popup._closeButton).one('click', function (e) {
            that._map.clickpopup = null;
        });
    },

    getPhotoContextPopupContent: function (filePath) {
        var moveText = t('maps', 'Move');
        var resetText = t('maps', 'Remove geo data');
        var res =
            '<ul filepath="' + filePath + '">' +
            '   <li>' +
            '       <button class="icon-link movephoto">' +
            '           <span>' + moveText + '</span>' +
            '       </button>' +
            '   </li>' +
            '   <li>' +
            '       <button class="icon-history resetphoto">' +
            '           <span>' + resetText + '</span>' +
            '       </button>' +
            '   </li>' +
            '</ul>';
        return res;
    },

    enterMovePhotoMode: function () {
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'crosshair');
        this.map.on('click', this.movePhotoClickMap);
        this.map.clickpopup = true;
        OC.Notification.showTemporary(t('maps', 'Click on the map to move the photo, press Esc to cancel'));
    },

    leaveMovePhotoMode: function () {
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        this.map.off('click', this.movePhotoClickMap);
        this.map.clickpopup = null;
        this.movingPhotoPath = null;
    },

    movePhotoClickMap: function (e) {
        var lat = e.latlng.lat;
        var lng = e.latlng.lng;
        var filePath = this.photosController.movingPhotoPath;
        this.photosController.leaveMovePhotoMode();
        this.photosController.placePhotos([filePath], [lat], [lng], false, true);
    },

    updateTimeFilterRange: function () {
        this.updateMyFirstLastDates();
        this.timeFilterController.updateSliderRangeFromController();
    },

    updateTimeFilterBegin: function (date) {
        if (date <= this.timeFilterEnd) {
            var i = this.photoMarkersFirstVisible;
            if (date < this.timeFilterBegin) {
                i = i - 1;
                while (i >= 0 && i <= this.photoMarkersLastVisible && this.photoMarkers[i].data.dateTaken >= date) {
                    this.photoLayer.addLayer(this.photoMarkers[i]);
                    i = i - 1;
                }
                this.photoMarkersFirstVisible = i + 1;
            } else {
                while (i < this.photoMarkers.length && i >= 0 && i <= this.photoMarkersLastVisible && this.photoMarkers[i].data.dateTaken < date) {
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

    updateTimeFilterEnd: function (date) {
        if (date >= this.timeFilterBegin) {
            var i = this.photoMarkersLastVisible;
            if (date < this.timeFilterEnd) {
                while (i >= 0 && i >= this.photoMarkersFirstVisible && this.photoMarkers[i].data.dateTaken > date) {
                    this.photoLayer.removeLayer(this.photoMarkers[i]);
                    i = i - 1;
                }
                this.photoMarkersLastVisible = i;
            } else {
                i = i + 1;
                while (i >= this.photoMarkersFirstVisible && i < this.photoMarkers.length && this.photoMarkers[i].data.dateTaken <= date) {
                    this.photoLayer.addLayer(this.photoMarkers[i]);
                    i = i + 1;
                }
                this.photoMarkersLastVisible = i - 1;
            }
            this.timeFilterEnd = date;
        } else {
            this.updateTimeFilterEnd(this.timeFilterBegin);
        }
    },

    callForImages: function () {
        this.photosRequestInProgress = true;
        $('#navigation-photos').addClass('icon-loading-small');
        $.ajax({
            url: generateUrl('apps/maps/photos'),
            type: 'GET',
            async: true,
            data: {
                myMapId: this.optionsController.myMapId
            },
            context: this
        }).done(function (response) {
            if (response.length == 0) {
                //showNoPhotosMessage();
            } else {
                this.addPhotosToMap(response);
            }
            this.photosDataLoaded = true;
        }).always(function (response) {
            this.photosRequestInProgress = false;
            $('#navigation-photos').removeClass('icon-loading-small');
        }).fail(function () {
            OC.Notification.showTemporary(t('maps', 'Failed to load photos'));
        });
    },

    /* Preview size 32x32 is used in files view, so it sould be generated */
    generateThumbnailUrl: function (filename) {
        return generateUrl('core') + '/preview.png?file=' + encodeURI(filename) + '&x=32&y=32';
    },

    /* Preview size 341x256 is commonly found in preview folder */
    generatePreviewUrl: function (fileId) {
        return generateUrl('core') + '/preview?fileId=' + fileId + '&x=341&y=256&a=1';
    },

    getImageIconUrl: function () {
        return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2';
    },

    contextPlacePhotosOrFolder: function (e) {
        var that = this.photosController;
        OC.dialogs.confirmDestructive(
            '',
            t('maps', 'What do you want to place?'),
            {
                type: OC.dialogs.YES_NO_BUTTONS,
                confirm: t('maps', 'Photo files'),
                confirmClasses: '',
                cancel: t('maps', 'Photo folders'),
            },
            function (result) {
                if (result) {
                    that.contextPlacePhotos(e);
                } else {
                    that.contextPlacePhotoFolder(e);
                }
            },
            true
        );
    },

    contextPlacePhotos: function (e) {
        var that = this;
        var latlng = e.latlng;
        OC.dialogs.filepicker(
            t('maps', 'Choose pictures to place'),
            function (targetPath) {
                that.placePhotos(targetPath, [latlng.lat], [latlng.lng]);
            },
            true,
            ['image/jpeg', 'image/tiff'],
            true
        );
    },

    contextPlacePhotoFolder: function (e) {
        var that = this;
        var latlng = e.latlng;
        OC.dialogs.filepicker(
            t('maps', 'Choose directory of pictures to place'),
            function (targetPath) {
                if (targetPath === '') {
                    targetPath = '/';
                }
                that.placePhotos([targetPath], [latlng.lat], [latlng.lng], true);
            },
            false,
            'httpd/unix-directory',
            true
        );
    },

    placePhotos: function (paths, lats, lngs, directory = false) {
        var that = this;
        $('#navigation-photos').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        var req = {
            paths: paths,
            lats: lats,
            lngs: lngs,
            myMapId: this.optionsController.myMapId,
            // we only have relative paths for photos displayed on a 'my-map'
            // so we tell it to the controller
            relative: (this.optionsController.myMapId !== null && moveAction),
            directory: directory
        };
        var url = generateUrl('/apps/maps/photos');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            OC.Notification.showTemporary(t('maps', '{nb} photos placed', {nb: response}));
            if (response > 0) {
                that.photosDataLoaded = false;
                for (var i = 0; i < that.photoMarkers.length; i++) {
                    that.photoLayer.removeLayer(that.photoMarkers[i]);
                }
                that.photoMarkers = [];
                that.photoMarkersOldest = null;
                that.photoMarkersNewest = null;
                that.photoMarkersFirstVisible = 0;
                that.photoMarkersLastVisible = -1;
                that.timeFilterBegin = 0;
                that.timeFilterEnd = Date.now();

                that.showLayer();
            }
        }).always(function (response) {
            $('#navigation-photos').removeClass('icon-loading-small');
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        }).fail(function (response) {
            OC.Notification.showTemporary(t('maps', 'Failed to place photos') + ': ' + response.responseText);
        });
    },

    resetPhotoCoords: function (paths) {
        var that = this;
        $('#navigation-photos').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        var req = {
            paths: paths,
            myMapId: this.optionsController.myMapId,
        };
        var url = generateUrl('/apps/maps/photos');
        $.ajax({
            type: 'DELETE',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            OC.Notification.showTemporary(t('maps', '{nb} photos reset', {nb: response}));
            if (response > 0) {
                that.photosDataLoaded = false;
                for (var i = 0; i < that.photoMarkers.length; i++) {
                    that.photoLayer.removeLayer(that.photoMarkers[i]);
                }
                that.photoMarkers = [];
                that.photoMarkersOldest = null;
                that.photoMarkersNewest = null;
                that.photoMarkersFirstVisible = 0;
                that.photoMarkersLastVisible = -1;
                that.timeFilterBegin = 0;
                that.timeFilterEnd = Date.now();

                that.showLayer();
            }
        }).always(function (response) {
            $('#navigation-photos').removeClass('icon-loading-small');
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        }).fail(function (response) {
            OC.Notification.showTemporary(t('maps', 'Failed to reset photos coordinates') + ': ' + response.responseText);
        });
    },

    getAutocompData: function () {
        var that = this;
        var mData;
        var data = [];
        if (this.map.hasLayer(this.photoLayer)) {
            this.photoLayer.eachLayer(function (l) {
                mData = l.data;
                data.push({
                    type: 'photo',
                    label: OC.basename(mData.path),
                    value: OC.basename(mData.path),
                    lat: mData.lat,
                    lng: mData.lng
                });
            });
        }
        return data;
    },

};

export default PhotosController;
