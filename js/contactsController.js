function ContactsController (optionsController, timeFilterController) {
    this.contact_MARKER_VIEW_SIZE = 40;
    this.contactsDataLoaded = false;
    this.contactsRequestInProgress = false;
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;
    this.contactMarkers = [];
    this.contactMarkersOldest = null;
    this.contactMarkersNewest = null;
    this.contactMarkersFirstVisible = 0;
    this.contactMarkersLastVisible = -1;
    this.timeFilterBegin = 0;
    this.timeFilterEnd = Date.now();
}

ContactsController.prototype = {

    initLayer : function(map) {
        this.map = map;
        var that = this;
        this.contactLayer = L.markerClusterGroup({
            iconCreateFunction : this.getClusterIconCreateFunction(),
            showCoverageOnHover : false,
            zoomToBoundsOnClick: false,
            maxClusterRadius: this.contact_MARKER_VIEW_SIZE + 10,
            icon: {
                iconSize: [this.contact_MARKER_VIEW_SIZE, this.contact_MARKER_VIEW_SIZE]
            }
        });
        this.contactLayer.on('click', this.getContactMarkerOnClickFunction());
        this.contactLayer.on('clusterclick', function (a) {
            if (a.layer.getChildCount() > 20) {
                a.layer.zoomToBounds();
            }
            else {
                a.layer.spiderfy();
            }
        });
        // click on contact menu entry
        $('body').on('click', '#toggleContactsButton, #navigation-contacts > a', function(e) {
            that.toggleLayer();
            that.optionsController.saveOptionValues({contactLayer: that.map.hasLayer(that.contactLayer)});
        });
        // click on menu button
        $('body').on('click', '.contactsMenuButton', function(e) {
            var wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });
    },

    updateMyFirstLastDates: function() {
        var firstVisible = this.contactMarkersFirstVisible;
        var lastVisible = this.contactMarkersLastVisible;
        var layerVisible = this.map.hasLayer(this.contactLayer);
        this.contactMarkersOldest = layerVisible ? this.contactMarkers[firstVisible].data.date : null;
        this.contactMarkersNewest = layerVisible ? this.contactMarkers[lastVisible].data.date : null;
    },

    showLayer: function() {
        if (!this.contactsDataLoaded && !this.contactsRequestInProgress) {
            this.callForContacts();
        }
        if (!this.map.hasLayer(this.contactLayer)) {
            this.map.addLayer(this.contactLayer);
        }
    },

    hideLayer: function() {
        if (this.map.hasLayer(this.contactLayer)) {
            this.map.removeLayer(this.contactLayer);
        }
    },

    toggleLayer: function() {
        if (this.map.hasLayer(this.contactLayer)) {
            this.hideLayer();
            // color of the eye
            $('#toggleContactsButton button').addClass('icon-toggle').attr('style', '');
        } else {
            this.showLayer();
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            $('#toggleContactsButton button').removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
        }
    },

    getContactMarkerOnClickFunction: function() {
        var _app = this;
        return function(evt) {
            var marker = evt.layer;
            var contactUrl = OC.generateUrl('/apps/contacts/All contacts/'+encodeURIComponent(marker.data.uid+"~contacts"));
            var win = window.open(contactUrl, '_blank');
            if (win) {
                win.focus();
            }
        };
    },

    getClusterIconCreateFunction: function() {
        var _app = this;
        return function(cluster) {
            var marker = cluster.getAllChildMarkers()[0].data;
            var iconUrl;
            if (marker.photo) {
                iconUrl = _app.generateAvatar(marker.photo) || _app.getImageIconUrl();
            } else {
                iconUrl = _app.getImageIconUrl();
            }
            var label = cluster.getChildCount();
            return new L.DivIcon(L.extend({
                className: 'leaflet-marker-contact cluster-marker',
                html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + label + '</span>'
            }, this.icon));
        };
    },

    createContactView: function(markerData) {
        var avatar;
        if (markerData.photo) {
            avatar = this.generateAvatar(markerData.photo) || this.getUserImageIconUrl();
        }
        else {
            avatar = this.getUserImageIconUrl();
        }
        //this.generatePreviewUrl(markerData.path);
        return L.divIcon(L.extend({
            html: '<div class="thumbnail" style="background-image: url(' + avatar + ');"></div>​',
            className: 'leaflet-marker-contact contact-marker'
        }, markerData, {
            iconSize: [this.contact_MARKER_VIEW_SIZE, this.contact_MARKER_VIEW_SIZE],
            iconAnchor:   [this.contact_MARKER_VIEW_SIZE / 2, this.contact_MARKER_VIEW_SIZE]
        }));
    },

    addContactsToMap : function(contacts) {
        var markers = this.prepareContactMarkers(contacts);
        this.contactMarkers.push.apply(this.contactMarkers, markers);
        this.contactMarkers.sort(function (a, b) { return a.data.date - b.data.date;});

        // we put them all in the layer
        this.contactMarkersFirstVisible = 0;
        this.contactMarkersLastVisible = this.contactMarkers.length - 1;
        this.contactLayer.addLayers(this.contactMarkers);

        this.updateTimeFilterRange();
        this.timeFilterController.setSliderToMaxInterval();
    },

    prepareContactMarkers : function(contacts) {
        var markers = [];
        for (var i = 0; i < contacts.length; i++) {

            var geo = [];
            if (contacts[i].GEO.substr(0,4) === "geo:") {
                geo = contacts[i].GEO.substr(4).split(",");
            } else {
                geo = contacts[i].GEO.split(";");
            }
            var date;
            if (contacts[i].hasOwnProperty('REV')) {
                date = Date.parse(contacts[i].REV);
            }
            else {
                date = new Date();
            }
            if (isNaN(date)) {
                var year = parseInt(contacts[i].REV.substr(0,4));
                var month = parseInt(contacts[i].REV.substr(4,2))-1;
                var day = parseInt(contacts[i].REV.substr(6,2))-1;
                var hour = parseInt(contacts[i].REV.substr(9,2))-1;
                var min = parseInt(contacts[i].REV.substr(11,2))-1;
                var sec = parseInt(contacts[i].REV.substr(13,2))-1;
                date = new Date(year,month,day,hour,min,sec);
                date = date.getTime();
            }

            var markerData = {
                name: contacts[i].FN,
                lat: parseFloat(geo[0]),
                lng: parseFloat(geo[1]),
                photo: contacts[i].PHOTO,
                uid: contacts[i].UID,
                date: date/1000,
            };
            var marker = L.marker([markerData.lat, markerData.lng], {
                icon: this.createContactView(markerData)
            });
            marker.data = markerData;
            var avatar = this.generateAvatar(marker.data.photo) || this.getUserImageIconUrl();
            var img = '<img class="tooltip-contact-avatar" src="' + avatar + '"/>' +
                '<p class="tooltip-contact-name">' + escapeHTML(basename(markerData.name)) + '</p>';
            marker.bindTooltip(img, {permanent: false, className: "leaflet-marker-contact-tooltip"});
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
            var i = this.contactMarkersFirstVisible;
            if (date < this.timeFilterBegin) {
                i = i-1;
                while (i >= 0 && i <= this.contactMarkersLastVisible && this.contactMarkers[i].data.date >= date) {
                    this.contactLayer.addLayer(this.contactMarkers[i]);
                    i = i-1;
                }
                this.contactMarkersFirstVisible = i + 1;
            }
            else {
                while (i < this.contactMarkers.length && i >= 0 && i <= this.contactMarkersLastVisible && this.contactMarkers[i].data.date < date) {
                    this.contactLayer.removeLayer(this.contactMarkers[i]);
                    i = i + 1;
                }
                this.contactMarkersFirstVisible = i;
            }
            this.timeFilterBegin = date;
        }
        else {
            this.updateTimeFilterBegin(this.timeFilterEnd);
        }
    },

    updateTimeFilterEnd: function (date){
        if (date >= this.timeFilterBegin) {
            var i = this.contactMarkersLastVisible;
            if (date < this.timeFilterEnd) {
                while (i >= 0 && i >= this.contactMarkersFirstVisible && this.contactMarkers[i].data.date > date ) {
                    this.contactLayer.removeLayer(this.contactMarkers[i]);
                    i = i-1;
                }
                this.contactMarkersLastVisible = i;
            }
            else {
                i = i+1;
                while (i >= this.contactMarkersFirstVisible && i < this.contactMarkers.length && this.contactMarkers[i].data.date <= date) {
                    this.contactLayer.addLayer(this.contactMarkers[i]);
                    i = i+1;
                }
                this.contactMarkersLastVisible = i - 1;
            }
            this.timeFilterEnd = date;
        }
        else {
            this.updateTimeFilterEnd(this.timeFilterBegin);
        }
    },

    callForContacts: function() {
        this.contactsRequestInProgress = true;
        $.ajax({
            'url' : OC.generateUrl('apps/maps/contacts'),
            'type': 'GET',
            'context' : this,
            'success': function(response) {
                if (response.length == 0) {
                    //showNocontactsMessage();
                } else {
                    this.addContactsToMap(response);
                }
                this.contactsDataLoaded = true;
            },
            'complete': function(response) {
                this.contactsRequestInProgress = false;
            }
        });
    },

    generateAvatar: function (data) {
        // data is supposed to be a base64 string
        // but if this is a 'user' contact, avatar is and address like
        // VALUE=uri:http://host/remote.php/dav/addressbooks/system/system/system/Database:toto.vcf?photo
        return data ? data.replace(/^VALUE=uri:/, '') : data;
    },

    getImageIconUrl: function() {
        return OC.generateUrl('/apps/theming/img/core/places') + '/contacts.svg?v=2';
    },

    getUserImageIconUrl: function() {
        return OC.generateUrl('/apps/theming/img/core/actions') + '/user.svg?v=2';
    },

    contextPlaceContact: function(e) {
        var that = this.contactsController;
        var lat = e.latlng.lat;
        var lng = e.latlng.lng;
        var popupText = '<input id="place-contact-input" type="text" />';
        this.openPopup(popupText, e.latlng);

        var req = {};
        var url = OC.generateUrl('/apps/maps/contacts-all');
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var d, c;
            var data = [];
            for (var i=0; i < response.length; i++) {
                c = response[i];
                d = {
                    id: c.URI,
                    label: c.FN,
                    value: c.FN,
                    uri: c.URI,
                    uid: c.UID,
                    bookid: c.BOOKID
                };
                data.push(d);
            }
            $('#place-contact-input').autocomplete({
                source: data,
                select: function (e, ui) {
                    var it = ui.item;
                    that.placeContact(it.bookid, it.uri, it.uid, lat, lng);
                }
            })
            $('#place-contact-input').focus().select();
        }).always(function (response) {
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to get contact list'));
        });
    },

    placeContact: function(bookid, uri, uid, lat, lng) {
        var that = this;
        $('#navigation-contacts').addClass('icon-loading-small');
        var req = {
            lat: lat,
            lng: lng,
            uid: uid
        };
        var url = OC.generateUrl('/apps/maps/contacts/'+bookid+'/'+uri);
        $.ajax({
            type: 'PUT',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
        }).always(function (response) {
            that.map.closePopup();
            $('#navigation-contacts').removeClass('icon-loading-small');
            that.reloadContacts();
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to place contact'));
        });
    },

    reloadContacts: function() {
        this.contactsDataLoaded = false;
        this.contactsRequestInProgress = false;

        for (var i=0; i < this.contactMarkers.length; i++) {
            this.contactLayer.removeLayer(this.contactMarkers[i]);
        }

        this.contactMarkers = [];
        this.contactMarkersOldest = null;
        this.contactMarkersNewest = null;
        this.contactMarkersFirstVisible = 0;
        this.contactMarkersLastVisible = -1;
        this.timeFilterBegin = 0;
        this.timeFilterEnd = Date.now();

        this.showLayer();
    },
};

