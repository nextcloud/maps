function FavoritesController(optionsController) {
    this.optionsController = optionsController;
    this.cluster = null;
    // indexed by category name
    this.categoryLayers = {};
    this.categoryDivIcon = {};
    this.categoryColors = {};
    this.categoryDeletionTimer = {};
    // indexed by favorite id
    this.markers = {};
    this.favorites = {};

    this.addFavoriteMode = false;
    this.addFavoriteCategory = '';

    this.defaultCategory = t('maps', 'no category');

    this.movingFavoriteId = null;

    // used by optionsController to know if favorite loading
    // was done before or after option restoration
    this.favoritesLoaded = false;
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
        // click on a category : zoom to bounds
        $('body').on('click', '.category-line .category-name', function(e) {
            var cat = $(this).text();
            that.zoomOnCategory(cat);
        });
        // toggle a category
        $('body').on('click', '.toggleCategoryButton', function(e) {
            var cat = $(this).parent().parent().parent().attr('category');
            that.toggleCategory(cat);
            that.saveEnabledCategories();
        });
        // click on + button
        $('body').on('click', '#addFavoriteButton', function(e) {
            if (that.addFavoriteMode) {
                that.leaveAddFavoriteMode();
            }
            else {
                if (that.movingFavoriteId !== null) {
                    that.leaveMoveFavoriteMode();
                }
                that.enterAddFavoriteMode();
            }
        });
        $('body').on('click', '.addFavoriteInCategory', function(e) {
            var cat = $(this).parent().parent().parent().parent().attr('category');
            if (that.movingFavoriteId !== null) {
                that.leaveMoveFavoriteMode();
            }
            that.enterAddFavoriteMode(cat);
        });
        // cancel favorite edition
        $('body').on('click', '.canceleditfavorite', function(e) {
            that.map.closePopup();
        });
        $('body').on('click', '.valideditfavorite', function(e) {
            that.editFavoriteFromPopup($(this));
            that.map.closePopup();
        });
        $('body').on('click', '.deletefavorite', function(e) {
            var favid = parseInt($(this).parent().find('table.editFavorite').attr('favid'));
            that.deleteFavoriteDB(favid);
        });
        $('body').on('click', '.movefavorite', function(e) {
            var tab = $(this).parent().find('table');
            var favid = tab.attr('favid');
            that.movingFavoriteId = favid;
            if (that.addFavoriteMode) {
                that.leaveAddFavoriteMode();
            }
            that.enterMoveFavoriteMode();
            that.map.closePopup();
        });
        // rename category
        $('body').on('click', '.renameCategory', function(e) {
            $(this).parent().parent().parent().parent().find('.renameCategoryInput').focus().select();
            $('#category-list > li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
        });
        $('body').on('click', '.renameCategoryOk', function(e) {
            var cat = $(this).parent().parent().parent().attr('category');
            $(this).parent().parent().parent().removeClass('editing').addClass('icon-loading-small');
            var newCategoryName = $(this).parent().find('.renameCategoryInput').val();
            that.renameCategory(cat, newCategoryName);
        });
        $('body').on('keyup', '.renameCategoryInput', function(e) {
            if (e.key === 'Enter') {
                var cat = $(this).parent().parent().parent().attr('category');
                $(this).parent().parent().parent().removeClass('editing').addClass('icon-loading-small');
                var newCategoryName = $(this).parent().find('.renameCategoryInput').val();
                that.renameCategory(cat, newCategoryName);
            }
        });
        $('body').on('click', '.renameCategoryClose', function(e) {
            $(this).parent().parent().parent().removeClass('editing');
        });
        // delete category
        $('body').on('click', '.deleteCategory', function(e) {
            var cat = $(this).parent().parent().parent().parent().attr('category');
            $(this).parent().parent().parent().parent().addClass('deleted');
            that.categoryDeletionTimer[cat] = new Timer(function() {
                that.deleteCategoryFavorites(cat);
            }, 7000);
        });
        $('body').on('click', '.undoDeleteCategory', function(e) {
            var cat = $(this).parent().parent().attr('category');
            $(this).parent().parent().removeClass('deleted');
            that.categoryDeletionTimer[cat].pause();
            delete that.categoryDeletionTimer[cat];
        });
        // click anywhere
        window.onclick = function(event) {
            if (!event.target.matches('.app-navigation-entry-utils-menu-button button')) {
                $('.app-navigation-entry-menu.open').removeClass('open');
            }
        };
        // toggle favorites
        $('body').on('click', '#toggleFavoritesButton', function(e) {
            that.toggleFavorites();
            that.optionsController.saveOptionValues({favoritesEnabled: that.map.hasLayer(that.cluster)});
        });
        // expand category list
        $('body').on('click', '#navigation-favorites > a', function(e) {
            that.toggleCategoryList();
            that.optionsController.saveOptionValues({favoriteCategoryListShow: $('#navigation-favorites').hasClass('open')});
        });
        $('body').on('click', '#navigation-favorites', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-favorites') {
                that.toggleCategoryList();
                that.optionsController.saveOptionValues({favoriteCategoryListShow: $('#navigation-favorites').hasClass('open')});
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

    zoomOnCategory: function(cat) {
        var catLayer = this.categoryLayers[cat];
        if (this.map.hasLayer(this.cluster) && this.map.hasLayer(catLayer)) {
            this.map.fitBounds(catLayer.getBounds(), {padding: [30, 30]});
        }
    },

    saveEnabledCategories: function() {
        var categoryList = [];
        var layer;
        for (var cat in this.categoryLayers) {
            layer = this.categoryLayers[cat];
            if (this.map.hasLayer(layer)) {
                categoryList.push(cat);
            }
        }
        var categoryStringList = categoryList.join('|');
        //console.log('save '+categoryStringList);
        this.optionsController.saveOptionValues({enabledFavoriteCategories: categoryStringList});
    },

    restoreCategoriesState: function(enabledCategoryList) {
        var cat;
        for (var i=0; i < enabledCategoryList.length; i++) {
            cat = enabledCategoryList[i];
            if (this.categoryLayers.hasOwnProperty(cat)) {
                this.toggleCategory(cat);
            }
        }
    },

    toggleCategory: function(cat) {
        var subgroup = this.categoryLayers[cat];
        var catNoSpace = cat.replace(' ', '-');
        var eyeButton = $('#category-list > li[category="'+cat+'"] .toggleCategoryButton button');
        var showAgain = false;
        if (this.map.hasLayer(this.cluster)) {
            // remove and add cluster to avoid a markercluster bug when spiderfied
            this.map.removeLayer(this.cluster);
            showAgain = true;
        }
        // hide category
        if (this.map.hasLayer(subgroup)) {
            this.map.removeLayer(subgroup);
            // color of the eye
            eyeButton.addClass('icon-toggle').attr('style', '');
        }
        // show category
        else {
            this.map.addLayer(subgroup);
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            eyeButton.removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
        }
        if (showAgain) {
            this.map.addLayer(this.cluster);
        }
    },

    // expand or fold categories in sidebar and save state in user options
    toggleCategoryList: function() {
        $('#navigation-favorites').toggleClass('open');
    },

    // toggle favorites layer on map and save state in user options
    toggleFavorites: function() {
        if (this.map.hasLayer(this.cluster)) {
            this.map.removeLayer(this.cluster);
            // color of the eye
            $('#toggleFavoritesButton button').addClass('icon-toggle').attr('style', '');
        }
        else {
            this.map.addLayer(this.cluster);
            // color of the eye
            var color = OCA.Theming.color.replace('#', '');
            var imgurl = OC.generateUrl('/svg/core/actions/toggle?color='+color);
            $('#toggleFavoritesButton button').removeClass('icon-toggle').css('background-image', 'url('+imgurl+')');
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
            that.favoritesLoaded = true;
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
    addCategory: function(rawName, enable=false) {
        var name = rawName.replace(' ', '-');

        // color
        var color = '0000EE';
        if (rawName.length > 1) {
            var hsl = getLetterColor(rawName[0], rawName[1]);
            color = hslToRgb(hsl.h/360, hsl.s/100, hsl.l/100);
        }
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

        // icon for markers
        this.categoryDivIcon[rawName] = L.divIcon({
            iconAnchor: [7, 7],
            className: 'favoriteMarker '+name+'CategoryMarker',
            html: ''
        });

        // side menu entry
        var imgurl = OC.generateUrl('/svg/core/actions/star?color='+color);
        var li = '<li class="category-line" id="'+name+'-category" category="'+rawName+'">' +
        '    <a href="#" class="category-name" id="'+name+'-category-name" style="background-image: url('+imgurl+')">'+rawName+'</a>' +
        '    <div class="app-navigation-entry-utils">' +
        '        <ul>' +
        '            <li class="app-navigation-entry-utils-counter">1</li>' +
        '            <li class="app-navigation-entry-utils-menu-button toggleCategoryButton" title="'+t('maps', 'Toggle category')+'">' +
        '                <button class="icon-toggle"></button>' +
        '            </li>' +
        '            <li class="app-navigation-entry-utils-menu-button categoryMenuButton">' +
        '                <button></button>' +
        '            </li>' +
        '        </ul>' +
        '    </div>' +
        '    <div class="app-navigation-entry-menu">' +
        '        <ul>' +
        '            <li>' +
        '                <a href="#" class="addFavoriteInCategory">' +
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
        '    <div class="app-navigation-entry-deleted">' +
        '        <div class="app-navigation-entry-deleted-description">'+t('maps', 'Category deleted')+'</div>' +
        '        <button class="app-navigation-entry-deleted-button icon-history undoDeleteCategory" title="Undo"></button>' +
        '    </div>' +
        '    <div class="app-navigation-entry-edit">' +
        '        <div>' +
        '            <input type="text" value="'+rawName+'" class="renameCategoryInput">' +
        '            <input type="submit" value="" class="icon-close renameCategoryClose">' +
        '            <input type="submit" value="" class="icon-checkmark renameCategoryOk">' +
        '        </div>' +
        '    </div>' +
        '</li>';

        $('#category-list').append(li);

        // enable if in saved options or if it should be enabled for another reason :
        // * added because a favorite was added by the user in this category which didn't exist
        // * added because a favorite was edited by the user and triggered creation of this category
        if (enable || this.optionsController.enabledFavoriteCategories.indexOf(rawName) !== -1) {
            this.toggleCategory(rawName);
        }
    },

    renameCategory: function(cat, newCategoryName) {
        var markers = this.categoryLayers[cat].getLayers();
        var favid, favname;
        for (var i=0; i < markers.length; i++) {
            favid = markers[i].favid;
            favname = this.favorites[favid].name;
            this.editFavoriteDB(favid, favname, null, newCategoryName, null, null);
        }
    },

    deleteCategoryFavorites: function(cat) {
        var markers = this.categoryLayers[cat].getLayers();
        var favid;
        for (var i=0; i < markers.length; i++) {
            favid = markers[i].favid;
            this.deleteFavoriteDB(favid);
        }
    },

    deleteCategory: function(cat) {
        this.map.removeLayer(this.categoryLayers[cat]);
        delete this.categoryLayers[cat];
        delete this.categoryDivIcon[cat];
        delete this.categoryColors[cat];
        $('#category-list #'+cat.replace(' ', '-')+'-category').fadeOut('slow', function() {
            $(this).remove();
        });
    },

    updateCategoryCounters: function() {
        var count;
        var total = 0;
        for (var cat in this.categoryLayers) {
            count = this.categoryLayers[cat].getLayers().length;
            $('#'+cat.replace(' ', '-')+'-category .app-navigation-entry-utils-counter').text(count);
            total = total + count;
        }
        //$('#navigation-favorites > .app-navigation-entry-utils .app-navigation-entry-utils-counter').text(total);
    },

    enterAddFavoriteMode: function(categoryName='') {
        this.addFavoriteCategory = categoryName;
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
        this.addFavoriteCategory = '';
    },

    addFavoriteClickMap: function(e) {
        //addPointDB(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6), null, null, null, null, moment());
        var defaultName = t('maps', 'no name');
        var categoryName = this.favoritesController.addFavoriteCategory;
        this.favoritesController.addFavoriteDB(categoryName, e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6), defaultName);
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
            that.addFavoriteMap(fav, true);
            that.updateCategoryCounters();
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to add favorite'));
        });
    },

    // add a marker to the corresponding layer
    addFavoriteMap: function(fav, enableCategory=false) {
        // manage category first
        cat = fav.category;
        if (!cat) {
            cat = this.defaultCategory;
        }
        if (!this.categoryLayers.hasOwnProperty(cat)) {
            this.addCategory(cat, enableCategory);
            if (enableCategory) {
                this.saveEnabledCategories();
            }
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
        var content = t('maps', 'Name') + ': ' + fav.name;
        if (fav.category) {
            content = content + '<br/>' + t('maps', 'Category') + ': ' + fav.category;
        }
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
        res = res + '<td><input role="category" type="text" value="' + (fav.category || '') + '"/></td>';
        res = res + '</tr>';
        res = res + '<tr title="' + t('phonetrack', 'Comment') + '">';
        res = res + '<td><i class="fa fa-comment" style="font-size: 15px;"></i></td>';
        res = res + '<td><textarea role="comment">' + (fav.comment || '') + '</textarea></td>';
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
            this.deleteCategory(cat);
            this.saveEnabledCategories();
        }
    },

    editFavoriteFromPopup: function(button) {
        var tab = button.parent().find('table');
        var favid = parseInt(tab.attr('favid'));
        var fav = this.favorites[favid];

        var newName = tab.find('input[role=name]').val();
        var newCategory = tab.find('input[role=category]').val();
        var newComment = tab.find('textarea[role=comment]').val();

        this.editFavoriteDB(favid, newName, newComment, newCategory, null, null);
    },

    editFavoriteDB: function(favid, name, comment, category, lat, lng) {
        var that = this;
        $('#navigation-favorites').addClass('icon-loading-small');
        var req = {
            name: name,
            extensions: null
        };
        if (comment !== null) {
            req.comment = comment;
        }
        if (category !== null) {
            req.category = category;
        }
        if (lat) {
            req.lat = lat;
        }
        if (lng) {
            req.lng = lng;
        }
        var url = OC.generateUrl('/apps/maps/favorites/'+favid);
        $.ajax({
            type: 'PUT',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.editFavoriteMap(favid, name, comment, category, lat, lng);

            that.updateCategoryCounters();
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to edit favorite'));
        });
    },

    editFavoriteMap: function(favid, name, comment, category, lat, lng) {
        if (name !== null) {
            this.favorites[favid].name = name;
        }
        if (comment !== null) {
            this.favorites[favid].comment = comment;
        }
        if (category !== null) {
            var oldCategory = this.favorites[favid].category || this.defaultCategory;
            var newCategory = category || this.defaultCategory;
            if (newCategory !== oldCategory) {
                var marker = this.markers[favid];
                this.categoryLayers[oldCategory].removeLayer(marker);
                var shouldSaveCategories = false;
                // delete old category if empty
                if (this.categoryLayers[oldCategory].getLayers().length === 0) {
                    this.deleteCategory(oldCategory);
                    shouldSaveCategories = true;
                }
                // create category if necessary
                if (!this.categoryLayers.hasOwnProperty(newCategory)) {
                    this.addCategory(newCategory, true);
                    shouldSaveCategories = true;
                }
                if (shouldSaveCategories) {
                    this.saveEnabledCategories();
                }
                marker.setIcon(this.categoryDivIcon[newCategory]);
                this.categoryLayers[newCategory].addLayer(marker);
                // the real value goes here
                this.favorites[favid].category = category;
            }
        }
        if (lat !== null && lng !== null) {
            this.favorites[favid].lat = lat;
            this.favorites[favid].lng = lng;
            var marker = this.markers[favid];
            marker.setLatLng([lat, lng]);
        }
    },

    enterMoveFavoriteMode: function() {
        $('.leaflet-container').css('cursor', 'crosshair');
        this.map.on('click', this.moveFavoriteClickMap);
        OC.Notification.showTemporary(t('maps', 'Click on the map to move the favorite, press ESC to cancel'));
    },

    leaveMoveFavoriteMode: function() {
        $('.leaflet-container').css('cursor', 'grab');
        this.map.off('click', this.moveFavoriteClickMap);
        this.movingFavoriteId = null;
    },

    moveFavoriteClickMap: function(e) {
        var lat = e.latlng.lat;
        var lng = e.latlng.lng;
        var favid = this.favoritesController.movingFavoriteId;
        var name = this.favoritesController.favorites[favid].name;
        this.favoritesController.editFavoriteDB(favid, name, null, null, lat, lng);
        this.favoritesController.leaveMoveFavoriteMode();
    },
}

function Timer(callback, mydelay) {
    var timerId, start, remaining = mydelay;

    this.pause = function() {
        window.clearTimeout(timerId);
        remaining -= new Date() - start;
    };

    this.resume = function() {
        start = new Date();
        window.clearTimeout(timerId);
        timerId = window.setTimeout(callback, remaining);
    };

    this.resume();
}

function getLetterColor(letter1, letter2) {
    var letter1Index = letter1.toLowerCase().charCodeAt(0);
    var letter2Index = letter2.toLowerCase().charCodeAt(0);
    var letterCoef = (letter1Index * letter2Index) % 100 / 100;
    var h = letterCoef * 360;
    var s = 75 + letterCoef * 10;
    var l = 50 + letterCoef * 10;
    return {h: Math.round(h), s: Math.round(s), l: Math.round(l)};
}

function hslToRgb(h, s, l) {
    var r, g, b;

    if(s == 0){
        r = g = b = l; // achromatic
    }else{
        var hue2rgb = function hue2rgb(p, q, t){
            if(t < 0) t += 1;
            if(t > 1) t -= 1;
            if(t < 1/6) return p + (q - p) * 6 * t;
            if(t < 1/2) return q;
            if(t < 2/3) return p + (q - p) * (2/3 - t) * 6;
            return p;
        }

        var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        var p = 2 * l - q;
        r = hue2rgb(p, q, h + 1/3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1/3);
    }
    var rgb = {r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255)};
    var hexStringR = rgb.r.toString(16);
    if (hexStringR.length % 2) {
        hexStringR = '0' + hexStringR;
    }
    var hexStringG = rgb.g.toString(16);
    if (hexStringG.length % 2) {
        hexStringG = '0' + hexStringG;
    }
    var hexStringB = rgb.b.toString(16);
    if (hexStringB.length % 2) {
        hexStringB = '0' + hexStringB;
    }
    return hexStringR+hexStringG+hexStringB;
}
