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
    this.addFavoriteMode = false;
    this.defaultCategory = t('maps', 'no category');
}

FavoritesController.prototype = {

    // set up favorites-related UI stuff
    initFavorites : function(map) {
        this.map = map;
        var that = this;
        // UI events
        // click on menu buttons
        $('body').on('click', '.favoritesMenuButton, .categoryMenuButton', function(e) {
            var wasOpen = $(this).parent().parent().parent().find('>.app-navigation-entry-menu').hasClass('open');
            $('.app-navigation-entry-menu.open').removeClass('open');
            if (!wasOpen) {
                $(this).parent().parent().parent().find('>.app-navigation-entry-menu').addClass('open');
            }
        });
        // click on a category
        $('body').on('click', '.category-line .category-name', function(e) {
            var cat = $(this).text();
            var subgroup = that.categoryLayers[cat];
            var line = $(this).parent();
            // remove and add cluster to avoid a markercluster bug when spiderfied
            that.map.removeLayer(that.cluster);
            if (that.map.hasLayer(subgroup)) {
                that.map.removeLayer(subgroup);
                line.removeClass('line-enabled').addClass('line-disabled');
            }
            else {
                that.map.addLayer(subgroup);
                line.removeClass('line-disabled').addClass('line-enabled');
            }
            that.map.addLayer(that.cluster);
        });
        // click on + button
        $('body').on('click', '#addFavoriteButton', function(e) {
            if (that.addFavoriteMode) {
                that.leaveAddFavoriteMode();
            }
            else {
                that.enterAddFavoriteMode();
            }
        });
        // click anywhere
        window.onclick = function(event) {
            if (!event.target.matches('.app-navigation-entry-utils-menu-button button')) {
                $('.app-navigation-entry-menu.open').removeClass('open');
            }
        };
        // toggle favorites
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
            zoomToBoundsOnClick: false,
            chunkedLoading: true
        });
        this.cluster.on('clusterclick', function (a) {
            a.layer.spiderfy();
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
            for (var i=0; i < response.length; i++) {
                fav = response[i];
                that.addFavoriteMap(fav);
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
        var li = '<li class="category-line line-enabled" id="'+name+'-category">' +
        '    <a href="#" class="category-name" id="'+name+'-category-name" style="background-image: url('+imgurl+')">'+rawName+'</a>' +
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

    // make the request
    addFavoriteDB: function(category, lat, lng, name, comment=null, extensions=null) {
        var that = this;
        $('#navigation-favorites').addClass('icon-loading-small');
        var req = {
            name: name,
            lat: lat,
            lng: lng,
            category: category,
            comment: comment,
            extensions: extensions
        };
        var url = OC.generateUrl('/apps/maps/favorites');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var fav = {
                id: response.id,
                name: name,
                lat: lat,
                lng: lng,
                category: category,
                comment: comment,
                extensions: extensions
            }
            that.addFavoriteMap(fav);
            that.updateCategoryCounters();
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to add favorite'));
        });
    },

    // add a marker to the corresponding layer
    addFavoriteMap: function(fav) {
        cat = fav.category;
        color = '0000EE';
        if (!cat) {
            cat = this.defaultCategory;
        }
        if (!this.categoryLayers.hasOwnProperty(cat)) {
            if (cat === this.defaultCategory) {
                color = OCA.Theming.color.replace('#', '');
            }
            this.addCategory(cat, color);
        }
        var marker = L.marker(L.latLng(fav.lat, fav.lng), {
            icon: this.categoryDivIcon[cat]
        });
        this.categoryLayers[cat].addLayer(marker);
        this.favorites[fav.id] = fav;
        this.markers[fav.id] = marker;
    },

    enterAddFavoriteMode: function() {
        $('.leaflet-container').css('cursor','crosshair');
        this.map.on('click', this.addFavoriteClickMap);
        $('#addFavoriteButton button').removeClass('icon-add').addClass('icon-history');
        $('#explainaddpoint').show();
        this.addFavoriteMode = true;
    },

    leaveAddFavoriteMode: function() {
        $('.leaflet-container').css('cursor','grab');
        this.map.off('click', this.addFavoriteClickMap);
        $('#addFavoriteButton button').addClass('icon-add').removeClass('icon-history');
        this.addFavoriteMode = false;
    },

    addFavoriteClickMap: function(e) {
        //addPointDB(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6), null, null, null, null, moment());
        var defaultName = t('maps', 'no name');
        this.favoritesController.addFavoriteDB(null, e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6), defaultName);
        this.favoritesController.leaveAddFavoriteMode();
    },

}
