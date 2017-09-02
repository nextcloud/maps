function PhotosController () {
    this.PHOTO_MARKER_VIEW_SIZE = 40;
    this.photosDataLoaded = false;
    this.photosRequestInProgress = false;
}
 
PhotosController.prototype = {

    appendToMap : function(map) {
        this.map = map;
        this.photoLayer = L.markerClusterGroup({
            iconCreateFunction : this.createClusterView,
            showCoverageOnHover : false,
            maxClusterRadius: this.PHOTO_MARKER_VIEW_SIZE + 10,
            icon: {						
                iconSize: [this.PHOTO_MARKER_VIEW_SIZE, this.PHOTO_MARKER_VIEW_SIZE]
			}
        });
        this.photoLayer.on('click', this.onPhotoViewClick);
        this.photoLayer.addTo(this.map);
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

    onPhotoViewClick : function(evt) {
        var img = L.Util.template('<img src="{url}"/>', evt.layer.data);
        var marker = evt.layer;
        //Workaround for https://github.com/Leaflet/Leaflet/issues/5484
        $(img).on('load', function() {
            marker.getPopup().update();
        });
        marker.bindPopup(img, {
            className: 'leaflet-popup-photo',
            maxWidth: "auto"
        }).openPopup();
    },

    createClusterView : function(cluster) {
        var thumbnailUrl = cluster.getAllChildMarkers()[0].data.thumbnail;
        var label = cluster.getChildCount();
        return new L.DivIcon(L.extend({
            className: 'leaflet-marker-photo cluster-marker', 
            html: '<div class="thumbnail" style="background-image: url(' + thumbnailUrl + ');"></div>​<span class="label">' + label + '</span>'
        }, this.icon));
    },

    createPhotoView: function(photo) {
        return L.divIcon(L.extend({
            html: '<div class="thumbnail" style="background-image: url(' + photo.thumbnail + ');"></div>​',
            className: 'leaflet-marker-photo photo-marker'
        }, photo, {						
            iconSize: [this.PHOTO_MARKER_VIEW_SIZE, this.PHOTO_MARKER_VIEW_SIZE],
            iconAnchor:   [this.PHOTO_MARKER_VIEW_SIZE / 2, this.PHOTO_MARKER_VIEW_SIZE]
        }));
    },

    addPhotosToMap : function(photos) {
        var markers = this.preparePhotoMarkers(photos);
        this.photoLayer.addLayers(markers);
    },

    preparePhotoMarkers : function(photos) {
        var markers = [];
        for (var i = 0; i < photos.length; i++) {
            var markerData = {
                lat: photos[i].lat,
                lng: photos[i].lng,
                url: this.generateImageUrl(photos[i].path),
                thumbnail: this.generateThumbnailUrl(photos[i].path),
                albumId: photos[i].folderId
            };
            var marker = L.marker(markerData, {
                icon: this.createPhotoView(markerData)
            });
            marker.data = markerData;
            markers.push(marker);
        }
        return markers;
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
        return "/index.php/core/preview.png?file=" + encodeURI(filename) + "&x=32&y=32";
    },

    /* Preview size 375x211 is used in files details view */
    generateImageUrl: function (filename) {
        return "/index.php/core/preview.png?file=" + encodeURI(filename) + "&x=375&y=211&a=1";
    }

};

