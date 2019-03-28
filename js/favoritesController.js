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
        // cancel favorite edition
        $('body').on('click', '.canceleditfavorite', function(e) {
            that.map.closePopup();
        });
        $('body').on('click', '.valideditfavorite', function(e) {
            that.map.closePopup();
        });
        $('body').on('click', '.deletefavorite', function(e) {
            var favid = parseInt($(this).parent().find('table.editFavorite').attr('favid'));
            that.deleteFavoriteDB(favid);
        });
        $('body').on('click', '.movefavorite', function(e) {
            that.map.closePopup();
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

    hexToRgb: function(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    },

    // add category in side menu
    // add layer
    // set color and icon
    addCategory: function(rawName) {
        var name = rawName.replace(' ', '-');

        // color
        var color = '0000EE';
        if (rawName === this.defaultCategory) {
            color = OCA.Theming.color.replace('#', '');
        }
        this.categoryColors[rawName] = color;
        var rgbc = this.hexToRgb('#'+color);
        var textcolor = 'black';
        if (rgbc.r + rgbc.g + rgbc.b < 3 * 80) {
            textcolor = 'white';
        }
        $('<style category="'+name+'">' +
            '.'+name+'CategoryMarker { ' +
            'background-color: #'+color+';}' +
            '.tooltipfav-' + name + ' {' +
            'background: rgba(' + rgbc.r + ', ' + rgbc.g + ', ' + rgbc.b + ', 0.5);' +
            'color: ' + textcolor + '; font-weight: bold; }' +
            '</style>').appendTo('body');

        // subgroup layer
        this.categoryLayers[rawName] = L.featureGroup.subGroup(this.cluster, []);
        this.map.addLayer(this.categoryLayers[rawName]);

        // icon for markers
        this.categoryDivIcon[rawName] = L.divIcon({
            iconAnchor: [7, 7],
            className: 'favoriteMarker '+name+'CategoryMarker',
            html: ''
        });

        // side menu entry
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
        // manage category first
        cat = fav.category;
        if (!cat) {
            cat = this.defaultCategory;
        }
        if (!this.categoryLayers.hasOwnProperty(cat)) {
            this.addCategory(cat);
        }

        // create the marker and related events
        // put favorite id as marker attribute
        var marker = L.marker(L.latLng(fav.lat, fav.lng), {
            icon: this.categoryDivIcon[cat]
        });
        marker.favid = fav.id;
        marker.on('mouseover', this.favoriteMouseover);
        marker.on('mouseout', this.favoriteMouseout);
        marker.on('click', this.favoriteMouseClick);

        // add to map and arrays
        this.categoryLayers[cat].addLayer(marker);
        this.favorites[fav.id] = fav;
        this.markers[fav.id] = marker;
    },

    favoriteMouseover: function(e) {
        var favid = e.target.favid;
        var fav = this._map.favoritesController.favorites[favid];
        var cat = fav.category ? fav.category.replace(' ', '-') : this._map.favoritesController.defaultCategory.replace(' ', '-');
        var favTooltip = this._map.favoritesController.getFavoriteTooltipContent(fav);
        e.target.bindTooltip(favTooltip, {className: 'tooltipfav-' + cat});
        e.target.openTooltip();
    },

    favoriteMouseout: function(e) {
        e.target.unbindTooltip();
        e.target.closeTooltip();
    },

    getFavoriteTooltipContent: function(fav) {
        var content = t('maps', 'Name') + ': ' + fav.name + '<br/>' +
            t('maps', 'Category') + ': ' + (fav.category || this.defaultCategory);
        if (fav.comment) {
            content = content + '<br/>' + t('maps', 'Comment') + ': ' + fav.comment;
        }
        return content;
    },

    favoriteMouseClick: function(e) {
        var favid = e.target.favid;
        var fav = this._map.favoritesController.favorites[favid];

        e.target.unbindPopup();
        var popupContent = this._map.favoritesController.getFavoritePopupContent(fav);
        e.target.bindPopup(popupContent, {closeOnClick: false});
        e.target.openPopup();
    },

    getFavoritePopupContent: function(fav) {
        var res = '<table class="editFavorite" favid="' + fav.id + '">';
        res = res + '<tr title="' + t('maps', 'Name') + '">';
        res = res + '<td><i class="fa fa-star" style="font-size: 15px;"></i></td>';
        res = res + '<td><input role="name" type="text" value="' + fav.name + '"/></td>';
        res = res + '</tr>';
        res = res + '<tr title="' + t('phonetrack', 'Category') + '">';
        res = res + '<td><i class="fa fa-th-list" style="font-size: 15px;"></i></td>';
        res = res + '<td><input role="name" type="text" value="' + fav.category + '"/></td>';
        res = res + '</tr>';
        res = res + '<tr title="' + t('phonetrack', 'Comment') + '">';
        res = res + '<td><i class="fa fa-comment" style="font-size: 15px;"></i></td>';
        res = res + '<td><textarea role="name">' + fav.comment + '</textarea></td>';
        res = res + '</tr>';
        res = res + '</table>';
        res = res + '<button class="valideditfavorite"><i class="fa fa-save" aria-hidden="true"></i> ' + t('maps', 'Save') + '</button>';
        res = res + '<button class="deletefavorite"><i class="fa fa-trash" aria-hidden="true" style="color:red;"></i> ' + t('maps', 'Delete') + '</button>';
        res = res + '<br/><button class="movefavorite"><i class="fa fa-arrows-alt" aria-hidden="true"></i> ' + t('maps', 'Move') + '</button>';
        res = res + '<button class="canceleditfavorite"><i class="fa fa-undo" aria-hidden="true" style="color:red;"></i> ' + t('maps', 'Cancel') + '</button>';
        return res;
    },

    deleteFavoriteDB: function(favid) {
        var that = this;
        $('#navigation-favorites').addClass('icon-loading-small');
        var req = {
        };
        var url = OC.generateUrl('/apps/maps/favorites/'+favid);
        $.ajax({
            type: 'DELETE',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.deleteFavoriteMap(favid);

            that.updateCategoryCounters();
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to delete favorite'));
        });
    },

    deleteFavoriteMap: function(favid) {
        var marker = this.markers[favid];
        var fav = this.favorites[favid];
        var cat = fav.category || this.defaultCategory;
        this.categoryLayers[cat].removeLayer(marker);
        delete this.markers[favid];
        delete this.favorites[favid];

        // delete category if empty
        if (this.categoryLayers[cat].getLayers().length === 0) {
            // TODO
            //this.deleteCategory(cat);
        }
    },
}
