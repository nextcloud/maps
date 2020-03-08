import { generateUrl } from '@nextcloud/router';

import { basename, formatAddress } from './utils';

function ContactsController (optionsController, searchController) {
    this.contact_MARKER_VIEW_SIZE = 40;
    this.contactLayer = null;
    this.contactsDataLoaded = false;
    this.contactsRequestInProgress = false;
    this.optionsController = optionsController;
    this.searchController = searchController;
    // indexed by group name, contains number of contacts in the group
    this.groupsCount = {'0': 0};
    this.groups = {};
    this.contactMarkers = [];

    this.movingBookid = null;
    this.movingUri = null;
    this.movingUid = null;
}

ContactsController.prototype = {

    initLayer: function(map) {
        this.map = map;
        var that = this;
        this.contactLayer = L.markerClusterGroup({
            iconCreateFunction : this.getClusterIconCreateFunction(),
            spiderfyOnMaxZoom: false,
            showCoverageOnHover : false,
            zoomToBoundsOnClick: false,
            maxClusterRadius: this.contact_MARKER_VIEW_SIZE + 10,
            icon: {
                iconSize: [this.contact_MARKER_VIEW_SIZE, this.contact_MARKER_VIEW_SIZE]
            }
        });
        this.contactLayer.on('click', this.getContactMarkerOnClickFunction());
        this.contactLayer.on('clusterclick', function (a) {
            if (a.layer.getChildCount() > 20 && that.map.getZoom() !== that.map.getMaxZoom()) {
                a.layer.zoomToBounds();
            }
            else {
                a.layer.spiderfy();
                that.map.clickpopup = true;
            }
        });
        // click on contact menu entry
        $('body').on('click', '#navigation-contacts > a', function(e) {
            that.toggleLayer();
            that.optionsController.saveOptionValues({contactLayer: that.map.hasLayer(that.contactLayer)});
            // expand group list if we just enabled favorites and category list was folded
            if (that.map.hasLayer(that.contactLayer) && !$('#navigation-contacts').hasClass('open')) {
                that.toggleGroupList();
                that.optionsController.saveOptionValues({contactGroupListShow: $('#navigation-contacts').hasClass('open')});
            }
        });
        // expand group list
        $('body').on('click', '#navigation-contacts', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-contacts') {
                that.toggleGroupList();
                that.optionsController.saveOptionValues({contactGroupListShow: $('#navigation-contacts').hasClass('open')});
            }
        });
        // toggle a group
        $('body').on('click', '.contact-group-line .contact-group-name', function(e) {
            var groupName = $(this).parent().attr('contact-group');
            that.toggleGroup(groupName, true);
            that.saveDisabledGroups();
            that.addMarkersToLayer();
        });
        // show/hide all groups
        $('body').on('click', '#toggle-all-contact-groups', function(e) {
            var allEnabled = true;
            for (var gn in that.groups) {
                if (!that.groups[gn].enabled) {
                    allEnabled = false;
                    break;
                }
            }

            if (allEnabled) {
                that.hideAllGroups();
            }
            else {
                that.showAllGroups();
            }
            that.saveDisabledGroups();
            that.optionsController.saveOptionValues({contactLayer: that.map.hasLayer(that.contactLayer)});
            that.addMarkersToLayer();
        });
        // zoom to group
        $('body').on('click', '.zoomContactGroupButton', function(e) {
            var groupName = $(this).parent().parent().parent().parent().attr('contact-group');
            that.zoomOnGroup(groupName);
        });
        // zoom to all contacts
        $('body').on('click', '#zoom-all-contact-groups', function(e) {
            that.zoomOnGroup();
        });
        // delete address
        $('body').on('click', '.deleteContactAddress', function(e) {
            var bookid = $(this).attr('bookid');
            var uri = $(this).attr('uri');
            var uid = $(this).attr('uid');
            var vcardAddress = $(this).attr('vcardaddress');
            that.deleteContactAddress(bookid, uri, uid, vcardAddress);
        });
        $('body').on('click', '#submitPlaceContactButton', function(e) {
            that.submitPlaceContact();
        });
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
            $('#navigation-contacts').removeClass('active');
            $('#navigation-contacts > .app-navigation-entry-utils .app-navigation-entry-utils-counter').hide();
            $('#map').focus();
        } else {
            this.showLayer();
            $('#navigation-contacts > .app-navigation-entry-utils .app-navigation-entry-utils-counter').show();
            $('#navigation-contacts').addClass('active');
        }
    },

    // expand or fold groups in sidebar
    toggleGroupList: function() {
        $('#navigation-contacts').toggleClass('open');
    },

    toggleGroup: function(groupName) {
        var groupLine = $('#contact-group-list > li[contact-group="'+groupName+'"]');
        var groupCounter = groupLine.find('.app-navigation-entry-utils-counter');
        var showAgain = false;
        if (this.map.hasLayer(this.contactLayer)) {
            // remove and add cluster to avoid a markercluster bug when spiderfied
            this.map.removeLayer(this.contactLayer);
            showAgain = true;
        }
        // hide
        if (this.groups[groupName].enabled) {
            this.groups[groupName].enabled = false;
            groupLine.removeClass('active');
            groupCounter.hide();
            $('#map').focus();
        }
        // show
        else {
            this.groups[groupName].enabled = true;
            groupLine.addClass('active');
            groupCounter.show();
        }
        if (showAgain) {
            this.map.addLayer(this.contactLayer);
        }
    },

    showAllGroups: function() {
        if (!this.map.hasLayer(this.contactLayer)) {
            this.showLayer();
        }
        for (var gn in this.groups) {
            if (!this.groups[gn].enabled) {
                this.toggleGroup(gn);
            }
        }
    },

    hideAllGroups: function() {
        for (var gn in this.groups) {
            if (this.groups[gn].enabled) {
                this.toggleGroup(gn);
            }
        }
    },

    saveDisabledGroups: function() {
        var groupList = [];
        for (var gn in this.groups) {
            if (!this.groups[gn].enabled) {
                groupList.push(gn);
            }
        }
        var groupStringList = groupList.join('|');
        this.optionsController.saveOptionValues({disabledContactGroups: groupStringList});
        // this is used when contacts are loaded again
        this.optionsController.disabledContactGroups = groupList;
    },

    zoomOnGroup: function(groupName=null) {
        // zoom on all groups only if there are contacts
        if (groupName === null && this.contactLayer.getLayers().length > 0) {
            var b = this.contactLayer.getBounds();
            this.map.fitBounds(b, { padding: [30, 30] });
        }
        // zoom on a specific group
        else {
            // enable the group if it was not
            if (!$('li.contact-group-line[contact-group="'+groupName+'"]').hasClass('active')) {
                this.toggleGroup(groupName);
                this.addMarkersToLayer();
                this.saveDisabledGroups();
            }
            // determine the bounds
            var lat, lng;
            var minLat = null;
            var maxLat = null;
            var minLng = null;
            var maxLng = null;
            if (this.contactMarkers.length > 0) {
                for (var i=0; i < this.contactMarkers.length; i++) {
                    // if contact is in the group we zoom on
                    if ((groupName === '0' && this.contactMarkers[i].data.groups.length === 0)
                        || this.contactMarkers[i].data.groups.indexOf(groupName) !== -1) {
                        lat = this.contactMarkers[i].data.lat;
                        lng = this.contactMarkers[i].data.lng;
                        if (minLat === null) {
                            minLat = lat;
                            maxLat = lat;
                            minLng = lng;
                            maxLng = lng;
                        }
                        else {
                            if (lat < minLat) {
                                minLat = lat;
                            }
                            if (lat > maxLat) {
                                maxLat = lat;
                            }
                            if (lng < minLng) {
                                minLng = lng;
                            }
                            if (lng > maxLng) {
                                maxLng = lng;
                            }
                        }
                    }
                }
            }
            if (minLat !== null) {
                var b = L.latLngBounds(L.latLng(minLat, minLng), L.latLng(maxLat, maxLng));
                this.map.fitBounds(b, {padding: [30, 30]});
            }
            else {
                OC.Notification.showTemporary(t('maps', 'There are no contacts to zoom on'));
            }
        }
    },

    getContactMarkerOnClickFunction: function() {
        var _app = this;
        return function(evt) {
            var marker = evt.layer;
            var popupContent = _app.getContactPopupContent(marker.data);
            marker.unbindTooltip();
            this._map.clickpopup = true;

            var popup = L.popup({
                closeOnClick: true,
                className: 'popovermenu open popupMarker contactPopup',
                offset: L.point(-5, 10)
            })
                .setLatLng(marker.getLatLng())
                .setContent(popupContent)
                .openOn(_app.map);
            $(popup._closeButton).one('click', function (e) {
                _app.map.clickpopup = null;
            });
        };
    },

    getClusterIconCreateFunction: function() {
        var _app = this;
        return function(cluster) {
            var marker = cluster.getAllChildMarkers()[0].data;
            var iconUrl = marker.avatar;
            var label = cluster.getChildCount();
            return new L.DivIcon(L.extend({
                className: 'leaflet-marker-contact cluster-marker',
                html: '<div class="thumbnail" style="background-image: url(' + iconUrl + ');"></div>​<span class="label">' + label + '</span>'
            }, this.icon));
        };
    },

    createContactView: function(markerData) {
        var avatar = markerData.avatar;
        //this.generatePreviewUrl(markerData.path);
        return L.divIcon(L.extend({
            html: '<div class="thumbnail" style="background-image: url(' + avatar + ');"></div>​',
            className: 'leaflet-marker-contact contact-marker'
        }, markerData, {
            iconSize: [this.contact_MARKER_VIEW_SIZE, this.contact_MARKER_VIEW_SIZE],
            iconAnchor:   [this.contact_MARKER_VIEW_SIZE / 2, this.contact_MARKER_VIEW_SIZE]
        }));
    },

    addContactsToMap: function(contacts) {
        var markers = this.prepareContactMarkers(contacts);
        $('#navigation-contacts .app-navigation-entry-utils-counter span').text(markers.length);
        for (var gn in this.groupsCount) {
            this.addGroup(gn);
        }
        this.contactMarkers.push.apply(this.contactMarkers, markers);
        this.contactMarkers.sort(function (a, b) { return a.data.date - b.data.date;});

        // we put them in the layer
        this.addMarkersToLayer();
    },

    addMarkersToLayer: function() {
        this.contactLayer.clearLayers();
        var displayedMarkers = [];
        var i, j, m;
        for (i=0; i < this.contactMarkers.length; i++) {
            m = this.contactMarkers[i];
            // not grouped
            if (m.data.groups.length === 0 && this.groups['0'].enabled) {
                displayedMarkers.push(m);
                continue;
            }
            // in at least a group
            else {
                for (j=0; j < m.data.groups.length; j++) {
                    if (this.groups[m.data.groups[j]].enabled) {
                        displayedMarkers.push(m);
                        continue;
                    }
                }
            }
        }
        this.contactLayer.addLayers(displayedMarkers);
    },

    addGroup: function(rawName, enable=false) {
        this.groups[rawName] = {};
        var name = rawName.replace(/\s+/g, '-');

        var color = '000000';
        var displayName = rawName;
        if (rawName === '0') {
            displayName = t('maps', 'Not grouped');
        }
        this.groups[rawName].color = color;

        // side menu entry
        var imgurl = generateUrl('/svg/core/places/contacts?color='+color);
        var li = '<li class="contact-group-line" id="'+name+'-contact-group" contact-group="'+rawName+'">' +
        '    <a href="#" class="contact-group-name" id="'+name+'-category-name" style="background-image: url('+imgurl+')">'+displayName+'</a>' +
        '    <div class="app-navigation-entry-utils">' +
        '        <ul>' +
        '            <li class="app-navigation-entry-utils-counter" style="display:none;">'+this.groupsCount[rawName]+'</li>' +
        '            <li class="app-navigation-entry-utils-menu-button contactGroupMenuButton">' +
        '                <button></button>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-menu">' +
        '        <ul>' +
        '            <li>' +
        '                <a href="#" class="zoomContactGroupButton">' +
        '                    <span class="icon-search"></span>' +
        '                    <span>'+t('maps', 'Zoom to bounds')+'</span>' +
        '                </a>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '</li>';

        var beforeThis = null;
        var rawLower = rawName.toLowerCase();
        $('#contact-group-list > li').each(function() {
            let groupName = $(this).attr('contact-group');
            if (rawLower.localeCompare(groupName) < 0) {
                beforeThis = $(this);
                return false;
            }
        });
        if (beforeThis !== null) {
            $(li).insertBefore(beforeThis);
        }
        else {
            $('#contact-group-list').append(li);
        }

        // enable if in saved options
        if (enable || this.optionsController.disabledContactGroups.indexOf(rawName) === -1) {
            this.toggleGroup(rawName);
        }
    },

    resetGroupList: function() {
        $('#contact-group-list li').remove();
    },

    prepareContactMarkers: function(contacts) {
        var j, groupName;
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

            // format address
            var adrTab = contacts[i].ADR.split(';');
            var formattedAddress = '';
            if (adrTab.length > 6) {
                // check if street name is set
                if(adrTab[2] !== '') {
                    formattedAddress += adrTab[2] + '<br>';
                }
                formattedAddress += adrTab[5] + ' ' + adrTab[3] + '<br>' + adrTab[4] + ' ' + adrTab[6];
            }

            var markerData = {
                name: contacts[i].FN,
                lat: parseFloat(geo[0]),
                lng: parseFloat(geo[1]),
                uid: contacts[i].UID,
                uri: contacts[i].URI,
                adr: contacts[i].ADR,
                has_photo: contacts[i].HAS_PHOTO,
                address: formattedAddress,
                addressType: contacts[i].ADRTYPE.toLowerCase(),
                bookid: contacts[i].BOOKID,
                bookuri: contacts[i].BOOKURI,
                date: date/1000,
                groups: contacts[i].GROUPS ? contacts[i].GROUPS.split(',') : []
            };
            // manage groups
            if (markerData.groups.length === 0) {
                this.groupsCount['0'] = this.groupsCount['0'] + 1;
            }
            else {
                for (j = 0; j < markerData.groups.length; j++) {
                    groupName = markerData.groups[j];
                    this.groupsCount[groupName] = this.groupsCount[groupName] ? this.groupsCount[groupName] + 1 : 1;
                }
            }
            if (contacts[i].HAS_PHOTO) {
                markerData.avatar = this.generateAvatar(markerData) || this.getUserImageIconUrl();
            }
            else {
                markerData.avatar = this.getLetterAvatarUrl(basename(markerData.name));
            }

            var marker = L.marker([markerData.lat, markerData.lng], {
                icon: this.createContactView(markerData)
            });

            // disabled for the moment
            //marker.on('contextmenu', this.onContactRightClick);
            marker.on('mouseover', this.onContactMouseover);
            marker.on('mouseout', this.onContactMouseout);
            marker.data = markerData;

            markers.push(marker);
        }
        return markers;
    },

    getContactTooltipContent: function(markerData) {
        var contactTooltip = '<img class="tooltip-contact-avatar" src="' + markerData.avatar + '" alt="" /><div class="tooltip-contact-content">';
        var contactName = '<h3 class="tooltip-contact-name">' + escapeHTML(basename(markerData.name)) + '</h3>';
        contactTooltip += contactName;
        if (markerData.addressType === 'home') {
            contactTooltip += '<p class="tooltip-contact-address-type">'+t('maps', 'Home')+'</p>';
        }
        else if (markerData.addressType === 'work') {
            contactTooltip += '<p class="tooltip-contact-address-type">'+t('maps', 'Work')+'</p>';
        }
        contactTooltip += '<p class="tooltip-contact-address">' + markerData.address + '</p></div>';
        return contactTooltip;
    },

    getContactPopupContent: function(markerData) {
        var deleteText = t('maps', 'Delete this address');
        var contactPopup = '<img class="tooltip-contact-avatar" src="' + markerData.avatar + '" alt="" /><div class="tooltip-contact-content">';
        var contactName = '<h3 class="tooltip-contact-name">' + escapeHTML(basename(markerData.name)) + '</h3>';
        contactPopup += contactName;
        if (markerData.addressType === 'home') {
            contactPopup += '<p class="tooltip-contact-address-type">'+t('maps', 'Home');
        }
        else if (markerData.addressType === 'work') {
            contactPopup += '<p class="tooltip-contact-address-type">'+t('maps', 'Work');
        }
        /* Hide contact address deletion for now
        contactPopup += '<span class="icon-delete deleteContactAddress" bookid="' + markerData.bookid + '" '+
            'uri="' + markerData.uri + '" uid="' + markerData.uid + '" vcardaddress="' + markerData.adr + '" '+
            'title="' + deleteText + '"></span>';*/
        contactPopup += '</p><p class="tooltip-contact-address">' + markerData.address + '</p>';
        var contactUrl = generateUrl('/apps/contacts/'+t('contacts', 'All contacts')+'/'+encodeURIComponent(markerData.uid+"~contacts"));
        contactPopup += '<a href="'+contactUrl+'" target="_blank">'+t('maps', 'Open in Contacts')+'</a></div>';
        return contactPopup;
    },

    onContactMouseover: function(e) {
        var data = e.target.data;
        if (!e.target.getPopup() || !e.target.getPopup().isOpen()) {
            var tooltipContent = this._map.contactsController.getContactTooltipContent(data);
            e.target.bindTooltip(tooltipContent, {
                permanent: true,
                className: 'leaflet-marker-contact-tooltip',
                direction: 'top',
                offset: L.point(0, 0)
            });
        }
    },

    onContactMouseout: function(e) {
        e.target.unbindTooltip();
    },

    /* hide delete address functionality on right click for now to keep simple
    onContactRightClick: function(e) {
        var data = e.target.data;
        var bookid = data.bookid;
        var uri = data.uri;
        var uid = data.uid;
        var vcardAddress = data.adr;

        e.target.unbindPopup();
        var popupContent = this._map.contactsController.getContactContextPopupContent(bookid, uri, uid, vcardAddress);
        e.target.bindPopup(popupContent, {
            closeOnClick: true,
            className: 'popovermenu open popupMarker',
            offset: L.point(-5, -19)
        });
        e.target.openPopup();
        this._map.clickpopup = true;
    },

    getContactContextPopupContent: function(bookid, uri, uid, vcardAddress) {
        var deleteText = t('maps', 'Delete this address');
        var res =
            '<ul bookid="' + bookid + '" uri="' + uri + '" uid="' + uid + '" vcardaddress="' + vcardAddress + '">' +
            '   <li>' +
            '       <button class="icon-delete deleteContactAddress" bookid="' + bookid + '" uri="' + uri + '" uid="' + uid + '" vcardaddress="' + vcardAddress + '">' +
            '           <span>' + deleteText + '</span>' +
            '       </button>' +
            '   </li>' +
            '</ul>';
        return res;
    },

    deleteContactAddress: function(bookid, uri, uid, vcardAddress) {
        var that = this;
        $('#navigation-contacts').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        var req = {
            uid: uid,
            adr: vcardAddress
        };
        var url = generateUrl('/apps/maps/contacts/'+bookid+'/'+uri);
        $.ajax({
            type: 'DELETE',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
        }).always(function (response) {
            that.map.closePopup();
            that.map.clickpopup = null;
            $('#navigation-contacts').removeClass('icon-loading-small');
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
            that.reloadContacts();
        }).fail(function(response) {
            OC.Notification.showTemporary(t('maps', 'Failed to delete contact address') + ': ' + response.responseText);
        });
    },*/

    callForContacts: function() {
        this.contactsRequestInProgress = true;
        $('#navigation-contacts').addClass('icon-loading-small');
        $.ajax({
            url: generateUrl('apps/maps/contacts'),
            type: 'GET',
            async: true,
            context: this
        }).done(function (response) {
            if (response.length == 0) {
                //showNocontactsMessage();
            } else {
                this.addContactsToMap(response);
            }
            this.contactsDataLoaded = true;
        }).always(function (response) {
            this.contactsRequestInProgress = false;
            $('#navigation-contacts').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load contacts'));
        });
    },

    generateAvatar: function (data) {
        // data is supposed to be a base64 string
        // but if this is a 'user' contact, avatar is and address like
        // VALUE=uri:http://host/remote.php/dav/addressbooks/system/system/system/Database:toto.vcf?photo
        //return data ? data.replace(/^VALUE=uri:/, '') : data;
        var url = generateUrl('/remote.php/dav/addressbooks/users/' + OC.getCurrentUser().uid +
                  '/' + data.bookuri + '/' + data.uri + '?photo').replace(/index\.php\//, '');
        return url;
    },

    getImageIconUrl: function() {
        return generateUrl('/apps/theming/img/core/places') + '/contacts.svg?v=2';
    },

    getUserImageIconUrl: function() {
        return generateUrl('/apps/theming/img/core/actions') + '/user.svg?v=2';
    },

    getLetterAvatarUrl: function(name) {
        return generateUrl('/apps/maps/contacts-avatar?name='+encodeURIComponent(name));
    },

    contextPlaceContact: function(e) {
        var that = this.contactsController;
        var lat = e.latlng.lat;
        var lng = e.latlng.lng;
        that.openPlaceContactPopup(lat, lng);
    },

    openPlaceContactPopup: function(lat, lng) {
        var that = this;
        var popupText = '<h3>' + t('maps', 'New contact address') + '</h3>';
        popupText += '<textarea id="placeContactPopupAddress"></textarea><br/>';
        popupText += '<button class="icon icon-user"></button>';
        popupText += '<input id="place-contact-input" placeholder="'+t('maps', 'Contact name')+'" type="text" />';
        popupText += '<button id="placeContactValidIcon" class="icon icon-checkmark"></button>';
        popupText += '<br/>';
        popupText += '<label for="addressTypeSelect">' + t('maps', 'Address type') + '</label>';
        popupText += '<select id="addressTypeSelect">';
        popupText += '<option value="home" selected>' + t('maps', 'Home') + '</option>';
        popupText += '<option value="work">' + t('maps', 'Work') + '</option>';
        popupText += '</select><br/><button id="submitPlaceContactButton">'+t('maps', 'Add address to contact')+'</button>';
        this.map.openPopup(popupText, [lat, lng]);
        this.map.clickpopup = true;

        that.currentPlaceContactAddress = null;
        that.currentPlaceContactLat = lat;
        that.currentPlaceContactLng = lng;
        that.currentPlaceContactFormattedAddress = null;
        that.currentPlaceContactContact = null;

        // get the reverse geocode address
        var strLatLng = lat+','+lng;
        that.searchController.geocode(strLatLng).then(function(results) {
            var address = {};
            if (results.address) {
                address = results.address;
                that.currentPlaceContactAddress = address;
                var strAddress = formatAddress(address);
                //console.log(address);
                $('#placeContactPopupAddress').text(strAddress);
                that.currentPlaceContactFormattedAddress = strAddress;
            }
        });
        // get the contact list
        var req = {};
        var url = generateUrl('/apps/maps/contacts-all');
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
                if (!c.READONLY) {
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
            }
            $('#place-contact-input').autocomplete({
                source: data,
                select: function (e, ui) {
                    var it = ui.item;
                    that.currentPlaceContactContact = ui.item;
                    $('#placeContactValidIcon').show();
                    //that.submitPlaceContactPopup(it.bookid, it.uri, it.uid, lat, lng, address, type, editedAddress);
                }
            })
            $('#place-contact-input').focus().select();
        }).always(function (response) {
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to get contact list'));
        });
    },

    submitPlaceContact: function() {
        var that = this;
        var lat = that.currentPlaceContactLat;
        var lng = that.currentPlaceContactLng;
        var currentContact = that.currentPlaceContactContact;
        var currentAddress = that.currentPlaceContactAddress;
        var currentFormattedAddress = that.currentPlaceContactFormattedAddress;
        var bookid = currentContact.bookid;
        var uri = currentContact.uri;
        var uid = currentContact.uid;
        var editedAddress = $('#placeContactPopupAddress').val().trim().replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ');
        var type = $('#addressTypeSelect').val();

        $('#submitPlaceContactButton').addClass('loading');

        // we didn't change the address => place
        if (currentFormattedAddress === editedAddress) {
            that.placeContact(bookid, uri, uid, lat, lng, currentAddress, type);
            that.map.panTo([lat, lng], { animate: true });
        }
        // we changed the address, search the new one
        else {
            that.searchController.search(editedAddress, 1).then(function(results) {
                var address = {};
                //console.log(results);
                // there was a result
                if (results.length > 0 && results[0].address && results[0].lat && results[0].lon) {
                    address = results[0].address;
                    //var strAddress = formatAddress(address);
                    lat = results[0].lat;
                    lng = results[0].lon;
                }
                // nope, no result, keep the original one
                else {
                    address = currentAddress;
                }
                that.placeContact(bookid, uri, uid, lat, lng, address, type);
                if (that.map.getBounds().contains(L.latLng(lat, lng))) {
                    that.map.panTo([lat, lng], { animate: true });
                }
                else {
                    that.map.flyTo([lat, lng], 15, { animate: true });
                }
            });
        }
    },

    placeContact: function(bookid, uri, uid, lat, lng, address, type='home') {
        var that = this;
        $('#navigation-contacts').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        var road = (address.road || '') + ' ' + (address.pedestrian || '') + ' ' + (address.suburb || '') + ' ' + (address.city_district || '');
        road = road.replace(/\s+/g, ' ').trim();
        var city = address.village || address.town || address.city || '';
        city = city.replace(/\s+/g, ' ').trim();
        var req = {
            lat: lat,
            lng: lng,
            uid: uid,
            attraction: address.attraction,
            house_number: address.house_number,
            road: road,
            postcode: address.postcode,
            city: city,
            state: address.state,
            country: address.country,
            type: type
        };
        var url = generateUrl('/apps/maps/contacts/'+bookid+'/'+uri);
        $.ajax({
            type: 'PUT',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
        }).always(function (response) {
            that.map.closePopup();
            that.map.clickpopup = null;
            $('#navigation-contacts').removeClass('icon-loading-small');
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
            that.reloadContacts();
        }).fail(function(response) {
            OC.Notification.showTemporary(t('maps', 'Failed to place contact') + ': ' + response.responseText);
        });
    },

    reloadContacts: function() {
        this.contactsDataLoaded = false;
        this.contactsRequestInProgress = false;

        for (var i=0; i < this.contactMarkers.length; i++) {
            this.contactLayer.removeLayer(this.contactMarkers[i]);
        }

        this.resetGroupList();

        this.groupsCount = {'0': 0};
        this.groups = {};
        this.contactMarkers = [];

        this.showLayer();
    },

    getAutocompData: function() {
        var that = this;
        var mData;
        var data = [];
        if (this.map.hasLayer(this.contactLayer)) {
            this.contactLayer.eachLayer(function (l) {
                mData = l.data;
                data.push({
                    type: 'contact',
                    label: mData.name,
                    value: mData.name,
                    lat: mData.lat,
                    lng: mData.lng
                });
            });
        }
        return data;
    },

};

export default ContactsController;
