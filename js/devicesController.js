function DevicesController(optionsController, timeFilterController) {
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;

    this.mainLayer = null;
    // indexed by device id
    // those actually added to map, those which get toggled
    this.mapDeviceLayers = {};
    // layers which actually contain lines/waypoints, those which get filtered
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
        // send my position on page load
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                var acc = position.coords.accuracy;
                that.sendMyPosition(lat, lng,acc);
            });
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
        var url = OC.generateUrl('/apps/maps/api/1.0/devices');
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

