function FavoritesController(optionsController) {
    this.optionsController = optionsController;
    this.categoryColors = {};
    // indexed by category name
    this.categoryLayers = {};
    // indexed by favorite id
    this.markers = {};
    // indexed by favorite id
    this.favorites = {};
}

FavoritesController.prototype = {

    // set up favorites-related UI stuff
    initFavorites : function(map) {
        this.map = map;
        // UI events
        // click on menu buttons
        $('body').on('click', '.favoritesMenuButton, .categoryMenuButton', function(e) {
            var wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });
        // click anywhere
        window.onclick = function(event) {
            if (!event.target.matches('.app-navigation-entry-utils-menu-button button')) {
                $('.app-navigation-entry-menu.open').removeClass('open');
            }
        };
        // set default category icon
        var imgurl = OC.generateUrl('/svg/core/actions/star?color=22EE33');
        $('#default-category').attr('style', 'background-image: url('+imgurl+')');
        // toggle favorites
        var that = this;
        $('body').on('click', '#navigation-favorites > a', function(e) {
            that.toggleFavorites();
        });
        $('body').on('click', '#navigation-favorites', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-favorites') {
                that.toggleFavorites();
            }
        });

        this.categoryLayers.defaultCategory = L.markerClusterGroup({
            //iconCreateFunction: function(cluster) {
            //    return L.divIcon({ html: '<div>' + cluster.getChildCount() + '</div>' });
            //},
            chunkedLoading: true
        });
        this.categoryColors.defaultCategory = '22EE33';
    },

    // expand or fold favorites in sidebar and save state in user options
    toggleFavorites: function() {
        $('#navigation-favorites').toggleClass('open');
        this.optionsController.saveOptionValues({favoritesEnabled: $('#navigation-favorites').hasClass('open')});
        if ($('#navigation-favorites').hasClass('open')) {
            for (var cat in this.categoryLayers) {
                this.map.addLayer(this.categoryLayers[cat]);
            }
        }
        else {
            for (var cat in this.categoryLayers) {
                this.map.removeLayer(this.categoryLayers[cat]);
            }
        }
    },

    // get favorites from server and create map layers
    // show map layers if favorites are enabled
    getFavorites: function() {
        var that = this;
        $('#navigation-favorites').addClass('icon-loading-small');
        var req = {};
        var url = OC.generateUrl('/apps/maps/favorites');
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var fav, marker;
            for (var i=0; i < response.length; i++) {
                fav = response[i];
                marker = L.marker(L.latLng(fav.lat, fav.lng));
                that.categoryLayers.defaultCategory.addLayer(marker);
                that.favorites[fav.id] = fav;
                that.markers[fav.id] = marker;
            }
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load favorites'));
        });
    },

    // make the request, add a marker to the corresponding layer
    addFavorite: function() {
    },

}
