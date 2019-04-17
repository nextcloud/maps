function DevicesController(optionsController, timeFilterController) {
    this.device_MARKER_VIEW_SIZE = 30;
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;

    this.mainLayer = null;
    // indexed by device id
    // those actually added to map, those which get toggled
    this.mapDeviceLayers = {};
    // layers which actually contain lines, those which get filtered
    this.deviceLayers = {};
    this.devices = {};

    this.firstDate = null;
    this.lastDate = null;

    // used by optionsController to know if devices loading
    // was done before or after option restoration
    this.deviceListLoaded = false;

    this.changingColorOf = null;
}

DevicesController.prototype = {

    initController : function(map) {
        this.map = map;
        this.mainLayer = L.featureGroup();
        var that = this;
        // click on menu buttons
        $('body').on('click', '.devicesMenuButton, .deviceMenuButton', function(e) {
            var wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });
        // click on a device name : zoom to bounds
        $('body').on('click', '.device-line .device-name', function(e) {
            var id = $(this).parent().attr('device');
            that.zoomOnDevice(id);
        });
        // toggle a device
        $('body').on('click', '.toggleDeviceButton', function(e) {
            var id = $(this).parent().parent().parent().attr('device');
            that.toggleDevice(id, true);
        });
        // toggle devices
        $('body').on('click', '#toggleDevicesButton', function(e) {
            that.toggleDevices();
            that.optionsController.saveOptionValues({devicesEnabled: that.map.hasLayer(that.mainLayer)});
            that.updateMyFirstLastDates();
        });
        // expand device list
        $('body').on('click', '#navigation-devices > a', function(e) {
            that.toggleDeviceList();
            that.optionsController.saveOptionValues({deviceListShow: $('#navigation-devices').hasClass('open')});
        });
        $('body').on('click', '#navigation-devices', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-devices') {
                that.toggleDeviceList();
                that.optionsController.saveOptionValues({deviceListShow: $('#navigation-devices').hasClass('open')});
            }
        });
        // color management
        $('body').on('click', '.changeDeviceColor', function(e) {
            var id = $(this).parent().parent().parent().parent().attr('device');
            that.askChangeDeviceColor(id);
        });
        $('body').on('change', '#devicecolorinput', function(e) {
            that.okColor();
        });
        // send my position on page load
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                var acc = position.coords.accuracy;
                that.sendMyPosition(lat, lng, acc);
            });
        }
    },

    // expand or fold device list in sidebar
    toggleDeviceList: function() {
        $('#navigation-devices').toggleClass('open');
    },

    // toggle devices general layer on map and save state in user options
    toggleDevices: function() {
        if (this.map.hasLayer(this.mainLayer)) {
            this.map.removeLayer(this.mainLayer);
            // color of the eye
            $('#toggleDevicesButton button').addClass('icon-toggle').attr('style', '');
        }
        else {
            if (!this.deviceListLoaded) {
                this.getDevices();
            }
            this.map.addLayer(this.mainLayer);
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            $('#toggleDevicesButton button').removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
        }
    },

    getDevices: function() {
        var that = this;
        $('#navigation-devices').addClass('icon-loading-small');
        var req = {};
        var url = OC.generateUrl('/apps/maps/devices');
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var i, device;
            for (i=0; i < response.length; i++) {
                device = response[i];
                that.addDeviceMap(device, false, true);
            }
            that.deviceListLoaded = true;
        }).always(function (response) {
            $('#navigation-devices').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load device list'));
        });
    },

    addDeviceMap: function(device, show=false, pageLoad=false) {
        var id = device.id;
        // color
        var color = device.color || OCA.Theming.color;
        this.devices[id] = device;
        this.devices[id].color = color;

        this.devices[id].icon = L.divIcon(L.extend({
            html: '<div class="thumbnail"></div>​',
            className: 'leaflet-marker-device device-marker device-marker-'+id
        }, null, {
            iconSize: [this.device_MARKER_VIEW_SIZE, this.device_MARKER_VIEW_SIZE],
            iconAnchor:   [this.device_MARKER_VIEW_SIZE / 2, this.device_MARKER_VIEW_SIZE]
        }));
        this.setDeviceCss(id, color);

        this.mapDeviceLayers[id] = L.featureGroup();
        this.deviceLayers[id] = L.featureGroup();
        this.devices[id].loaded = false;
        this.mapDeviceLayers[id].addLayer(this.deviceLayers[id]);

        var name = device.user_agent;

        // side menu entry
        var imgurl = OC.generateUrl('/svg/core/clients/phone?color='+color.replace('#', ''));
        var li = '<li class="device-line" id="'+name+'-device" device="'+id+'" name="'+name+'">' +
        '    <a href="#" class="device-name" id="'+name+'-device-name" style="background-image: url('+imgurl+')">'+name+'</a>' +
        '    <div class="app-navigation-entry-utils">' +
        '        <ul>' +
        '            <li class="app-navigation-entry-utils-menu-button toggleDeviceButton" title="'+t('maps', 'Toggle device')+'">' +
        '                <button class="icon-toggle"></button>' +
        '            </li>' +
        '            <li class="app-navigation-entry-utils-menu-button deviceMenuButton">' +
        '                <button></button>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-menu">' +
        '        <ul>' +
        '            <li>' +
        '                <a href="#" class="changeDeviceColor">' +
        '                    <span class="icon-rename"></span>' +
        '                    <span>'+t('maps', 'Change device color')+'</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="deleteDevice">' +
        '                    <span class="icon-delete"></span>' +
        '                    <span>'+t('maps', 'Delete')+'</span>' +
        '                </a>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-deleted">' +
        '        <div class="app-navigation-entry-deleted-description">'+t('maps', 'Device deleted')+'</div>' +
        '        <button class="app-navigation-entry-deleted-button icon-history undoDeleteDevice" title="Undo"></button>' +
        '    </div>' +
        '</li>';

        var beforeThis = null;
        var nameLower = name.toLowerCase();
        var deviceName;
        $('#device-list > li').each(function() {
            deviceName = $(this).attr('name');
            if (nameLower.localeCompare(deviceName) < 0) {
                beforeThis = $(this);
                return false;
            }
        });
        if (beforeThis !== null) {
            $(li).insertBefore(beforeThis);
        }
        else {
            $('#device-list').append(li);
        }

        // enable if in saved options or if it should be enabled for another reason
        if (show || this.optionsController.enabledDevices.indexOf(id) !== -1) {
            this.toggleDevice(id, false, pageLoad);
        }
    },

    setDeviceCss: function(id, color) {
        $('style[device='+id+']').remove();

        var imgurl = OC.generateUrl('/svg/core/clients/phone?color='+color.replace('#', ''));
        var rgbc = hexToRgb(color);
        var textcolor = 'black';
        if (rgbc.r + rgbc.g + rgbc.b < 3 * 80) {
            textcolor = 'white';
        }
        $('<style device="' + id + '">' +
            '.tooltip-dev-' + id + ' { ' +
            'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.5);' +
            'color: '+textcolor+'; font-weight: bold;' +
            ' }' +
            '.devline' + id + ' {' +
            'stroke: ' + color + ';' +
            '}' +
            '.device-marker-'+id+' { ' +
            'border-color: '+color+';}' +
            '.device-marker-'+id+'::after {' +
            'border-color: '+color+' transparent !important;}' +
            '.device-marker-'+id+' .thumbnail { ' +
            'background-image: url(' + imgurl + ');}' +
            '</style>').appendTo('body');
    },

    saveEnabledDevices: function(additionalIds=[]) {
        var deviceList = [];
        var layer;
        for (var id in this.mapDeviceLayers) {
            layer = this.mapDeviceLayers[id];
            if (this.mainLayer.hasLayer(layer)) {
                deviceList.push(id);
            }
        }
        for (var i=0; i < additionalIds.length; i++) {
            deviceList.push(additionalIds[i]);
        }
        var deviceStringList = deviceList.join('|');
        this.optionsController.saveOptionValues({enabledDevices: deviceStringList});
        // this is used when devices are loaded again
        this.optionsController.enabledDevices = deviceList;
    },

    restoreDevicesState: function(enabledDeviceList) {
        var id;
        for (var i=0; i < enabledDeviceList.length; i++) {
            id = enabledDeviceList[i];
            if (this.mapDeviceLayers.hasOwnProperty(id)) {
                this.toggleDevice(id, false, true);
            }
        }
    },

    toggleDevice: function(id, save=false, pageLoad=false) {
        if (!this.devices[id].loaded) {
            this.loadDevicePoints(id, save, pageLoad);
        }
        this.toggleMapDeviceLayer(id);
        if (save) {
            this.saveEnabledDevices();
            this.updateMyFirstLastDates();
        }
    },

    toggleMapDeviceLayer: function(id) {
        var mapDeviceLayer = this.mapDeviceLayers[id];
        var eyeButton = $('#device-list > li[device="'+id+'"] .toggleDeviceButton button');
        // hide device
        if (this.mainLayer.hasLayer(mapDeviceLayer)) {
            this.mainLayer.removeLayer(mapDeviceLayer);
            // color of the eye
            eyeButton.addClass('icon-toggle').attr('style', '');
        }
        // show device
        else {
            this.mainLayer.addLayer(mapDeviceLayer);
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            eyeButton.removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
        }
    },

    loadDevicePoints: function(id, save=false, pageLoad=false) {
        var that = this;
        $('#device-list > li[device="'+id+'"]').addClass('icon-loading-small');
        var req = {};
        var url = OC.generateUrl('/apps/maps/devices/'+id);
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.addPoints(id, response);
            that.devices[id].loaded = true;
            that.updateMyFirstLastDates(pageLoad);
        }).always(function (response) {
            $('#device-list > li[device="'+id+'"]').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load device points'));
        });
    },

    addPoints: function(id, points) {
        var lastPoint = points[points.length - 1];
        this.devices[id].marker = L.marker([lastPoint.lat, lastPoint.lng, lastPoint.id], {
                icon: this.devices[id].icon
        });
        this.devices[id].marker.devid = id;
        this.devices[id].marker.on('mouseover', this.deviceMarkerMouseover);
        this.devices[id].marker.on('mouseout', this.deviceMarkerMouseout);
        //this.devices[id].marker.on('click', this.favoriteMouseClick);
        // points data indexed by point id
        this.devices[id].points = {};
        // points coordinates (with id as third element)
        this.devices[id].pointsLatLngId = [];
        for (var i=0; i < points.length; i++) {
            this.devices[id].pointsLatLngId.push([points[i].lat, points[i].lng, points[i].id]);
            this.devices[id].points[points[i].id] = points[i];
        }
        this.devices[id].line = L.polyline(this.devices[id].pointsLatLngId, {
            weight: 4,
            opacity : 1,
            className: 'devline'+id,
        });
        this.deviceLayers[id].addLayer(this.devices[id].marker);
        this.deviceLayers[id].addLayer(this.devices[id].line);
    },

    updateMyFirstLastDates: function(pageLoad=false) {
        if (!this.map.hasLayer(this.mainLayer)) {
            this.firstDate = null;
            this.lastDate = null;
            return;
        }

        var id;

        // we update dates only if nothing is currently loading
        for (id in this.mapDeviceLayers) {
            if (this.mainLayer.hasLayer(this.mapDeviceLayers[id]) && !this.devices[id].loaded) {
                return;
            }
        }

        var initMinDate = Math.floor(Date.now() / 1000) + 1000000
        var initMaxDate = 0;

        var first = initMinDate;
        var last = initMaxDate;
        var fpId, lpId, firstPoint, lastPoint;
        for (id in this.mapDeviceLayers) {
            if (this.mainLayer.hasLayer(this.mapDeviceLayers[id]) && this.devices[id].loaded) {
                fpId = this.devices[id].pointsLatLngId[0][2];
                lpId = this.devices[id].pointsLatLngId[this.devices[id].pointsLatLngId.length - 1][2];
                firstPoint = this.devices[id].points[fpId];
                lastPoint = this.devices[id].points[lpId];
                if (firstPoint.timestamp && firstPoint.timestamp < first) {
                    first = firstPoint.timestamp;
                }
                if (lastPoint.timestamp && lastPoint.timestamp > last) {
                    last = lastPoint.timestamp;
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
        if (pageLoad) {
            this.timeFilterController.updateSliderRangeFromController();
            this.timeFilterController.setSliderToMaxInterval();
        }
    },

    updateFilterDisplay: function() {
        var startFilter = this.timeFilterController.valueBegin;
        var endFilter = this.timeFilterController.valueEnd;
        var id, i, pointsLLI, points, latLngToDisplay;
        for (id in this.devices) {
            if (this.devices[id].loaded) {
                latLngToDisplay = [];
                pointsLLI = this.devices[id].pointsLatLngId;
                points = this.devices[id].points;
                i = 0;
                while (i < pointsLLI.length && points[pointsLLI[i][2]].timestamp < startFilter) {
                    i++;
                }
                while (i < pointsLLI.length && points[pointsLLI[i][2]].timestamp <= endFilter) {
                    latLngToDisplay.push(pointsLLI[i]);
                    i++;
                }
                if (latLngToDisplay.length > 0) {
                    this.devices[id].line.setLatLngs(latLngToDisplay);
                    this.devices[id].marker.setLatLng(latLngToDisplay[latLngToDisplay.length - 1]);
                    if (!this.deviceLayers[id].hasLayer(this.devices[id].line)) {
                        this.deviceLayers[id].addLayer(this.devices[id].line);
                    }
                    if (!this.deviceLayers[id].hasLayer(this.devices[id].marker)) {
                        this.deviceLayers[id].addLayer(this.devices[id].marker);
                    }
                }
                else {
                    this.deviceLayers[id].removeLayer(this.devices[id].marker);
                    this.deviceLayers[id].removeLayer(this.devices[id].line);
                }
            }
        }
    },

    sendMyPosition: function(lat, lng, acc) {
        var that = this;
        var ts = Math.floor(Date.now() / 1000);
        var req = {
            lat: lat,
            lng: lng,
            acc: acc,
            timestamp: ts
        };
        var url = OC.generateUrl('/apps/maps/devices');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            // TODO get new positions
        }).always(function (response) {
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to send current position'));
        });
    },

    zoomOnDevice: function(id) {
        if (this.mainLayer.hasLayer(this.mapDeviceLayers[id])) {
            this.map.fitBounds(this.mapDeviceLayers[id].getBounds(), {padding: [30, 30]});
            this.mapDeviceLayers[id].bringToFront();
        }
    },

    askChangeDeviceColor: function(id) {
        this.changingColorOf = id;
        var currentColor = this.devices[id].color;
        $('#devicecolorinput').val(currentColor);
        $('#devicecolorinput').click();
    },

    okColor: function() {
        var color = $('#devicecolorinput').val();
        var id = this.changingColorOf;
        this.devices[id].color = color;
        this.changeDeviceColor(id, color);
    },

    changeDeviceColor: function(id, color) {
        var that = this;
        $('#device-list > li[device="'+id+'"]').addClass('icon-loading-small');
        var req = {
            color: color
        };
        var url = OC.generateUrl('/apps/maps/devices/'+id);
        $.ajax({
            type: 'PUT',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var imgurl = OC.generateUrl('/svg/core/clients/phone?color='+color.replace('#', ''));
            $('#device-list > li[device='+id+'] .device-name').attr('style', 'background-image: url('+imgurl+')');

            that.setDeviceCss(id, color);
        }).always(function (response) {
            $('#device-list > li[device="'+id+'"]').removeClass('icon-loading-small');
        }).fail(function(response) {
            OC.Notification.showTemporary(t('maps', 'Failed to change device color') + ': ' + response.responseText);
        });
    },

    deviceMarkerMouseover: function(e) {
        var id = e.target.devid;
        var pointId = e.target.getLatLng().alt;
        var device = this._map.devicesController.devices[id];
        var markerTooltip = this._map.devicesController.getDeviceMarkerTooltipContent(device, pointId);
        e.target.bindTooltip(markerTooltip, {className: 'tooltip-dev-' + id});
        e.target.openTooltip();
    },

    deviceMarkerMouseout: function(e) {
        e.target.unbindTooltip();
        e.target.closeTooltip();
    },

    getDeviceMarkerTooltipContent: function(device, pointId) {
        var point = device.points[pointId];
        var content = '⊙ ' + t('maps', 'User agent') + ': ' + brify(device.user_agent, 30);
        content = content + '<br/>' + '⊙ ' + t('maps', 'Date') + ': ' + (new Date(point.timestamp * 1000)).toIsoString();
        return content;
    },

}

