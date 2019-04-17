function DevicesController(optionsController, timeFilterController) {
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;

    this.mainLayer = null;
    // indexed by device id
    // those actually added to map, those which get toggled
    this.mapDeviceLayers = {};
    // layers which actually contain lines, those which get filtered
    this.deviceLayers = {};
    this.deviceColors = {};
    this.deviceDivIcon = {};
    this.devices = {};

    this.firstDate = null;
    this.lastDate = null;

    // used by optionsController to know if devices loading
    // was done before or after option restoration
    this.deviceListLoaded = false;
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
        // expand track list
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

    // toggle tracks general layer on map and save state in user options
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
        // color
        var color = device.color || OCA.Theming.color;
        this.deviceDivIcon[device.id] = L.divIcon({
            iconAnchor: [12, 25],
            className: 'trackWaypoint trackWaypoint-'+device.id,
            html: ''
        });
        this.devices[device.id] = device;

        this.mapDeviceLayers[device.id] = L.featureGroup();
        this.deviceLayers[device.id] = L.featureGroup();
        this.deviceLayers[device.id].loaded = false;
        this.mapDeviceLayers[device.id].addLayer(this.deviceLayers[device.id]);

        var name = device.user_agent;

        // side menu entry
        var imgurl = OC.generateUrl('/svg/core/clients/phone?color='+color.replace('#', ''));
        var li = '<li class="device-line" id="'+name+'-device" device="'+device.id+'" name="'+name+'">' +
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
        if (show || this.optionsController.enabledDevices.indexOf(device.id) !== -1) {
            this.toggleDevice(device.id, false, pageLoad);
        }
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
        // this is used when tracks are loaded again
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
        var deviceLayer = this.deviceLayers[id];
        if (!deviceLayer.loaded) {
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
        // show track
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
            that.deviceLayers[id].loaded = true;
            that.updateMyFirstLastDates(pageLoad);
        }).always(function (response) {
            $('#device-list > li[device="'+id+'"]').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load device points'));
        });
    },

    addPoints: function(id, points) {
        console.log('add points for device '+id);
        console.log(points);
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
            if (this.mainLayer.hasLayer(this.mapDeviceLayers[id]) && !this.deviceLayers[id].loaded) {
                return;
            }
        }

        var initMinDate = Math.floor(Date.now() / 1000) + 1000000
        var initMaxDate = 0;

        var first = initMinDate;
        var last = initMaxDate;
        for (id in this.mapDeviceLayers) {
            if (this.mainLayer.hasLayer(this.mapDeviceLayers[id]) && this.deviceLayers[id].loaded && this.devices[id].firstDate) {
                if (this.devices[id].firstDate < first) {
                    first = this.devices[id].firstDate;
                }
                if (this.devices[id].lastDate > last) {
                    last = this.device[id].lastDate;
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

}

