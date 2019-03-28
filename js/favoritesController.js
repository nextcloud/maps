function FavoritesController(optionsController) {
    this.optionsController = optionsController;
    this.cluster = null;
    // indexed by category name
    this.categoryLayers = {};
    this.categoryDivIcon = {};
    this.categoryColors = {};
    // indexed by favorite id
    this.markers = {};
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

        this.cluster = L.markerClusterGroup({
            //iconCreateFunction: function(cluster) {
            //    return L.divIcon({ html: '<div>' + cluster.getChildCount() + '</div>' });
            //},
            maxClusterRadius: 20,
            chunkedLoading: true
        });
    },

    // expand or fold favorites in sidebar and save state in user options
    toggleFavorites: function() {
        $('#navigation-favorites').toggleClass('open');
        this.optionsController.saveOptionValues({favoritesEnabled: $('#navigation-favorites').hasClass('open')});
        if ($('#navigation-favorites').hasClass('open')) {
            this.map.addLayer(this.cluster);
            //for (var cat in this.categoryLayers) {
            //    this.map.addLayer(this.categoryLayers[cat]);
            //}
        }
        else {
            this.map.removeLayer(this.cluster);
            //for (var cat in this.categoryLayers) {
            //    this.map.removeLayer(this.categoryLayers[cat]);
            //}
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
            var fav, marker, cat, color;
            var defaultCategory = t('maps', 'no category');
            for (var i=0; i < response.length; i++) {
                fav = response[i];
                cat = fav.category;
                color = '0000EE';
                if (!cat) {
                    cat = defaultCategory;
                }
                if (!that.categoryLayers.hasOwnProperty(cat)) {
                    if (cat === defaultCategory) {
                        color = OCA.Theming.color.replace('#', '');
                    }
                    that.addCategory(cat, color);
                }
                marker = L.marker(L.latLng(fav.lat, fav.lng), {
                    icon: that.categoryDivIcon[cat]
                });
                that.categoryLayers[cat].addLayer(marker);
                that.favorites[fav.id] = fav;
                that.markers[fav.id] = marker;
            }
            that.updateCategoryCounters();
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load favorites'));
        });
    },

    // add category in side menu
    // add layer
    // set color and icon
    addCategory: function(rawName, color) {
        var name = rawName.replace(' ', '-');
        this.categoryColors[rawName] = color;
        $('<style category="'+name+'">' +
            '.'+name+'CategoryMarker { ' +
            'background-color: #'+color+';' +
            '}</style>').appendTo('body');

        this.categoryLayers[rawName] = L.featureGroup.subGroup(this.cluster, []);
        this.map.addLayer(this.categoryLayers[rawName]);

        this.categoryDivIcon[rawName] = L.divIcon({
            iconAnchor: [7, 7],
            className: 'favoriteMarker '+name+'CategoryMarker',
            html: ''
        });
        var imgurl = OC.generateUrl('/svg/core/actions/star?color='+color);
        var li = '<li id="'+name+'-category">' +
        '    <a href="#" id="'+name+'-category-name" style="background-image: url('+imgurl+')">'+rawName+'</a>' +
        '    <div class="app-navigation-entry-utils">' +
        '        <ul>' +
        '            <li class="app-navigation-entry-utils-counter">1</li>' +
        '            <li class="app-navigation-entry-utils-menu-button categoryMenuButton">' +
        '                <button></button>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-menu">' +
        '        <ul>' +
        '            <li>' +
        '                <a href="#" class="addFavorite">' +
        '                    <span class="icon-add"></span>' +
        '                    <span>'+t('maps', 'Add a favorite')+'</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="renameCategory">' +
        '                    <span class="icon-rename"></span>' +
        '                    <span>'+t('maps', 'Rename')+'</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="deleteCategory">' +
        '                    <span class="icon-delete"></span>' +
        '                    <span>'+t('maps', 'Delete')+'</span>' +
        '                </a>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '</li>';

        $('#category-list').append(li);
    },

    updateCategoryCounters: function() {
        var count;
        var total = 0;
        for (var cat in this.categoryLayers) {
            count = this.categoryLayers[cat].getLayers().length;
            $('#'+cat.replace(' ', '-')+'-category .app-navigation-entry-utils-counter').text(count);
            total = total + count;
        }
        $('#navigation-favorites > .app-navigation-entry-utils .app-navigation-entry-utils-counter').text(total);
    },

    // make the request, add a marker to the corresponding layer
    addFavorite: function(category, ) {
    },

}
