function FavoritesController(optionsController) {
    this.optionsController = optionsController;
}

FavoritesController.prototype = {

    // set up favorites-related UI stuff
    initFavorites : function(map) {
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
    },

    // expand or fold favorites in sidebar and save state in user options
    // TODO show/hide layers
    toggleFavorites: function() {
        $('#navigation-favorites').toggleClass('open');
        this.optionsController.saveOptionValues({favoritesEnabled: $('#navigation-favorites').hasClass('open')});
    },

    // get favorites from server and create map layers
    // show map layers if favorites are enabled
    getFavorites: function() {
    },

    // make the request, add a marker to the corresponding layer
    addFavorite: function() {
    },

}
