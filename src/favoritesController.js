import { generateUrl } from '@nextcloud/router';

import { Timer, getLetterColor, hslToRgb } from './utils';

function FavoritesController(optionsController, timeFilterController) {
    this.CLUSTER_MARKER_VIEW_SIZE = 27;
    this.optionsController = optionsController;
    this.timeFilterController = timeFilterController;
    this.cluster = null;
    // indexed by category name
    this.categoryLayers = {};
    this.categoryDivIcon = {};
    this.categoryColors = {};
    this.categoryDeletionTimer = {};
    // indexed by category name and then by favorite id
    this.categoryMarkers = {};
    // indexed by favorite id
    this.markers = {};
    this.favorites = {};

    this.firstDate = null;
    this.lastDate = null;

    this.addFavoriteMode = false;
    this.addFavoriteCategory = null;

    this.defaultCategory = t('maps', 'Personal');
    this.lastUsedCategory = null;

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
        // toggle favorites
        $('body').on('click', '#navigation-favorites > a', function(e) {
            that.toggleFavorites();
            that.optionsController.saveOptionValues({favoritesEnabled: that.map.hasLayer(that.cluster)});
            that.updateTimeFilterRange();
            that.timeFilterController.setSliderToMaxInterval();
            // expand category list if we just enabled favorites and category list was folded
            if (that.map.hasLayer(that.cluster) && !$('#navigation-favorites').hasClass('open')) {
                that.toggleCategoryList();
                that.optionsController.saveOptionValues({favoriteCategoryListShow: $('#navigation-favorites').hasClass('open')});
            }
        });
        // expand category list
        $('body').on('click', '#navigation-favorites', function(e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-favorites') {
                that.toggleCategoryList();
                that.optionsController.saveOptionValues({favoriteCategoryListShow: $('#navigation-favorites').hasClass('open')});
            }
        });
        // toggle a category
        $('body').on('click', '.category-line .category-name', function(e) {
            var cat = $(this).text();
            that.toggleCategory(cat, true);
            that.saveEnabledCategories();
        });
        // zoom to category
        $('body').on('click', '.zoomCategoryButton', function(e) {
            var cat = $(this).parent().parent().parent().parent().attr('category');
            that.zoomOnCategory(cat);
        });
        // show/hide all categories
        $('body').on('click', '#toggle-all-categories', function(e) {
            var allEnabled = true;
            for (var cat in that.categoryLayers) {
                if (!that.map.hasLayer(that.categoryLayers[cat])) {
                    allEnabled = false;
                    break;
                }
            }

            if (allEnabled) {
                that.hideAllCategories();
            }
            else {
                that.showAllCategories();
            }
            that.saveEnabledCategories();
            that.optionsController.saveOptionValues({favoritesEnabled: that.map.hasLayer(that.cluster)});
        });
        // export a category
        $('body').on('click', '.exportCategoryButton', function(e) {
            var cat = $(this).parent().parent().parent().parent().attr('category');
            that.exportCategory(cat);
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
                that.enterAddFavoriteMode(that.defaultCategory);
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
            that.map.clickpopup = null;
            that.map.closePopup();
        });
        $('body').on('click', '.valideditfavorite', function(e) {
            that.editFavoriteFromPopup($(this));
            that.map.clickpopup = null;
            that.map.closePopup();
        });
        $('body').on('click', '.deletefavorite', function(e) {
            var favid = parseInt($(this).parent().parent().attr('favid'));
            that.deleteFavoriteDB(favid);
            that.map.clickpopup = null;
            that.map.closePopup();
        });
        $('body').on('click', '.valideditdeletefavorite', function(e) {
            var favid = parseInt($(this).parent().parent().attr('favid'));
            that.deleteFavoriteDB(favid);
            that.map.clickpopup = null;
            that.map.closePopup();
        });
        $('body').on('click', '.movefavorite', function(e) {
            var ul = $(this).parent().parent();
            var favid = ul.attr('favid');
            that.movingFavoriteId = favid;
            if (that.addFavoriteMode) {
                that.leaveAddFavoriteMode();
            }
            that.enterMoveFavoriteMode();
            that.map.closePopup();
        });
        // key events on popup fields
        $('body').on('keyup', 'input[role=category], input[role=name]', function(e) {
            if (e.key === 'Enter') {
                that.editFavoriteFromPopup($(this).parent().parent().parent().parent().find('.valideditfavorite'));
                that.map.clickpopup = null;
                that.map.closePopup();
            }
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
            var newCategoryName = $(this).parent().find('.renameCategoryInput').val() || that.defaultCategory;
            that.renameCategoryDB(cat, newCategoryName);
        });
        $('body').on('keyup', '.renameCategoryInput', function(e) {
            if (e.key === 'Enter') {
                var cat = $(this).parent().parent().parent().attr('category');
                $(this).parent().parent().parent().removeClass('editing').addClass('icon-loading-small');
                var newCategoryName = $(this).parent().find('.renameCategoryInput').val() || that.defaultCategory;
                that.renameCategoryDB(cat, newCategoryName);
            }
            else if (e.key === 'Escape') {
                $(this).parent().parent().parent().removeClass('editing');
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
                that.deleteCategoryFavoritesDB(cat);
            }, 7000);
        });
        $('body').on('click', '.undoDeleteCategory', function(e) {
            var cat = $(this).parent().parent().attr('category');
            $(this).parent().parent().removeClass('deleted');
            that.categoryDeletionTimer[cat].pause();
            delete that.categoryDeletionTimer[cat];
        });
        // export favorites
        $('body').on('click', '#export-displayed-favorites', function(e) {
            that.exportDisplayedFavorites();
        });

        // import favorites
        $('body').on('click', '#import-favorites', function(e) {
            OC.dialogs.filepicker(
                t('maps', 'Import favorites from gpx (OsmAnd, Nextcloud Maps) or kmz/kml (F-Droid Maps, Maps.me, Marble)'),
                function(targetPath) {
                    that.importFavorites(targetPath);
                },
                false,
                ['application/gpx+xml', 'application/vnd.google-earth.kmz', 'application/vnd.google-earth.kml+xml'],
                true
            );
        });

        this.cluster = L.markerClusterGroup({
            iconCreateFunction: this.getClusterIconCreateFunction(),
            spiderfyOnMaxZoom: false,
            maxClusterRadius: 28,
            zoomToBoundsOnClick: false,
            chunkedLoading: true,
            icon: {
                iconSize: [this.CLUSTER_MARKER_VIEW_SIZE, this.CLUSTER_MARKER_VIEW_SIZE]
            }
        });
        this.cluster.on('clusterclick', function (a) {
            if (a.layer.getChildCount() > 20 && that.map.getZoom() !== that.map.getMaxZoom()) {
                a.layer.zoomToBounds();
            }
            else {
                a.layer.spiderfy();
                that.map.clickpopup = true;
            }
        });
    },

    getClusterIconCreateFunction: function() {
        var that = this;
        return function(cluster) {
            var fid = parseInt(cluster.getAllChildMarkers()[0].favid);
            var category = that.favorites[fid].category;
            category = category.replace(/\s+/g, '-');
            var label = cluster.getChildCount();
            return new L.DivIcon(L.extend({
                iconAnchor: [14, 14],
                className: 'leaflet-marker-favorite-cluster cluster-marker',
                html: '<div class="favoriteClusterMarker '+category+'CategoryMarker"></div>​<span class="label">' + label + '</span>'
            }, this.icon));
        };
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
        this.optionsController.saveOptionValues({enabledFavoriteCategories: categoryStringList});
        // this is used when favorites are loaded again (when importing for example)
        this.optionsController.enabledFavoriteCategories = categoryList;
    },

    showAllCategories: function() {
        if (!this.map.hasLayer(this.cluster)) {
            this.toggleFavorites();
        }
        for (var cat in this.categoryLayers) {
            if (!this.map.hasLayer(this.categoryLayers[cat])) {
                this.toggleCategory(cat);
            }
        }
        this.updateMyFirstLastDates();
    },

    hideAllCategories: function() {
        for (var cat in this.categoryLayers) {
            if (this.map.hasLayer(this.categoryLayers[cat])) {
                this.toggleCategory(cat);
            }
        }
        this.updateMyFirstLastDates();
    },

    toggleCategory: function(cat, updateSlider=false) {
        var subgroup = this.categoryLayers[cat];
        var catLine = $('#category-list > li[category="'+cat+'"]');
        var catName = catLine.find('.category-name');
        var catCounter = catLine.find('.app-navigation-entry-utils-counter');
        var showAgain = false;
        if (this.map.hasLayer(this.cluster)) {
            // remove and add cluster to avoid a markercluster bug when spiderfied
            this.map.removeLayer(this.cluster);
            showAgain = true;
        }
        // hide category
        if (this.map.hasLayer(subgroup)) {
            this.map.removeLayer(subgroup);
            catName.removeClass('active');
            catCounter.hide();
            $('#map').focus();
        }
        // show category
        else {
            this.map.addLayer(subgroup);
            catName.addClass('active');
            catCounter.show();
        }
        if (showAgain) {
            this.map.addLayer(this.cluster);
        }
        if (updateSlider) {
            this.updateTimeFilterRange();
            this.timeFilterController.setSliderToMaxInterval();
        }
    },

    // expand or fold categories in sidebar and save state in user options
    toggleCategoryList: function() {
        $('#navigation-favorites').toggleClass('open');
    },

    // toggle favorites layer on map and save state in user options
    toggleFavorites: function() {
        if (!this.favoritesLoaded) {
            this.getFavorites();
        }
        if (this.map.hasLayer(this.cluster)) {
            this.map.removeLayer(this.cluster);
            $('#navigation-favorites').removeClass('active');
            $('#map').focus();
        }
        else {
            this.map.addLayer(this.cluster);
            $('#navigation-favorites').addClass('active');
        }
    },

    updateMyFirstLastDates: function() {
        if (!this.map.hasLayer(this.cluster)) {
            this.firstDate = null;
            this.lastDate = null;
            return;
        }

        var id, cat;

        var first = Math.floor(Date.now() / 1000) + 1000000;
        var last = 0;
        for (cat in this.categoryMarkers) {
            if (this.map.hasLayer(this.categoryLayers[cat])) {
                for (id in this.categoryMarkers[cat]) {
                    if (this.favorites[id].date_created < first) {
                        first = this.favorites[id].date_created;
                    }
                    if (this.favorites[id].date_created > last) {
                        last = this.favorites[id].date_created;
                    }
                }
            }
        }
        if (first !== (Math.floor(Date.now() / 1000) + 1000000)
            && last !== 0) {
            this.firstDate = first;
            this.lastDate = last;
        }
        else {
            this.firstDate = null;
            this.lastDate = null;
        }
    },

    updateTimeFilterRange: function() {
        this.updateMyFirstLastDates();
        this.timeFilterController.updateSliderRangeFromController();
    },

    // add/remove markers from layers considering current filter values
    updateFilterDisplay: function() {
        var startFilter = this.timeFilterController.valueBegin;
        var endFilter = this.timeFilterController.valueEnd;

        var cat, favid, markers, i, date_created;
        // markers to hide
        for (cat in this.categoryLayers) {
            markers = this.categoryLayers[cat].getLayers();
            for (i=0; i < markers.length; i++) {
                favid = markers[i].favid;
                date_created = this.favorites[favid].date_created;
                if (date_created < startFilter || date_created > endFilter) {
                    this.categoryLayers[cat].removeLayer(markers[i]);
                }
            }
        }

        // markers to show
        for (cat in this.categoryMarkers) {
            for (favid in this.categoryMarkers[cat]) {
                date_created = this.favorites[favid].date_created;
                if (date_created >= startFilter && date_created <= endFilter) {
                    this.categoryLayers[cat].addLayer(this.categoryMarkers[cat][favid]);
                }
            }
        }
    },

    // get favorites from server and create map layers
    // show map layers if favorites are enabled
    getFavorites: function() {
        var that = this;
        $('#navigation-favorites').addClass('icon-loading-small');
        var req = {};
        var url = generateUrl('/apps/maps/favorites');
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
            that.updateTimeFilterRange();
            that.timeFilterController.setSliderToMaxInterval();
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load favorites'));
        });
    },

    // add category in side menu
    // add layer
    // set color and icon
    addCategory: function(rawName, enable=false) {
        var name = rawName.replace(/\s+/g, '-');

        // color
        var color = '0000EE';
        if (rawName.length > 1) {
            var hsl = getLetterColor(rawName[0], rawName[1]);
            color = hslToRgb(hsl.h/360, hsl.s/100, hsl.l/100);
        }
        if (rawName === this.defaultCategory) {
            color = (OCA.Theming ? OCA.Theming.color : '#0082c9').replace('#', '');
        }
        this.categoryColors[rawName] = color;
        $('<style category="'+name+'">' +
            '.'+name+'CategoryMarker { ' +
            'background-color: #'+color+';' +
            '}' +
            '.tooltipfav-' + name + ' {' +
            'border: 2px solid #'+color+';' +
            '}' +
            '</style>').appendTo('body');

        // subgroup layer
        this.categoryLayers[rawName] = L.featureGroup.subGroup(this.cluster, []);
        this.categoryMarkers[rawName] = {};

        // icon for markers
        this.categoryDivIcon[rawName] = L.divIcon({
            iconAnchor: [9, 9],
            className: 'leaflet-marker-favorite',
            html: '<div class="favoriteMarker '+name+'CategoryMarker"></div>'
        });

        // side menu entry
        var imgurl = generateUrl('/svg/core/actions/star?color='+color);
        var li = '<li class="category-line" id="'+name+'-category" category="'+rawName+'">' +
        '    <a href="#" class="category-name" id="'+name+'-category-name" style="background-image: url('+imgurl+')">'+rawName+'</a>' +
        '    <div class="app-navigation-entry-utils">' +
        '        <ul>' +
        '            <li class="app-navigation-entry-utils-counter" style="display:none;">1</li>' +
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
        '                <a href="#" class="zoomCategoryButton">' +
        '                    <span class="icon-search"></span>' +
        '                    <span>'+t('maps', 'Zoom to bounds')+'</span>' +
        '                </a>' +
        '            </li>' +
        '            <li>' +
        '                <a href="#" class="exportCategoryButton">' +
        '                    <span class="icon-category-office"></span>' +
        '                    <span>'+t('maps', 'Export')+'</span>' +
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

        var beforeThis = null;
        var rawLower = rawName.toLowerCase();
        $('#category-list > li').each(function() {
            var catName = $(this).attr('category');
            if (rawLower.localeCompare(catName) < 0) {
                beforeThis = $(this);
                return false;
            }
        });
        if (beforeThis !== null) {
            $(li).insertBefore(beforeThis);
        }
        else {
            $('#category-list').append(li);
        }

        // enable if in saved options or if it should be enabled for another reason :
        // * added because a favorite was added by the user in this category which didn't exist
        // * added because a favorite was edited by the user and triggered creation of this category
        if (enable || this.optionsController.enabledFavoriteCategories.indexOf(rawName) !== -1) {
            this.toggleCategory(rawName);
        }
    },

    renameCategoryDB: function(cat, newCategoryName) {
        var that = this;
        var origCatList = [cat];
        $('#navigation-favorites').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        var req = {
            categories: origCatList,
            newName: newCategoryName
        };
        var url = generateUrl('/apps/maps/favorites-category');
        $.ajax({
            type: 'PUT',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var markers = that.categoryMarkers[cat];
            var favid, favname;
            for (favid in markers) {
                that.editFavoriteMap(favid, null, null, newCategoryName, null, null);
            }

            that.updateCategoryCounters();
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to rename category'));
        });
    },

    deleteCategoryFavoritesDB: function(cat) {
        var markers = this.categoryMarkers[cat];
        var favids = [];
        for (var favid in markers) {
            favids.push(favid);
        }
        var that = this;
        $('#navigation-favorites').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        var req = {
            ids: favids
        };
        var url = generateUrl('/apps/maps/favorites');
        $.ajax({
            type: 'DELETE',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.deleteCategoryMap(cat, true);
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to delete category favorites'));
        });
    },

    deleteCategoryMap: function(cat, updateSlider=false) {
        // favorites (just in case the category is not empty)
        var favids = [];
        for (favid in this.categoryMarkers[cat]) {
            favids.push(favid);
        }
        for (var i=0; i < favids.length; i++) {
            var favid = favids[i];
            this.categoryLayers[cat].removeLayer(this.markers[favid]);
            delete this.favorites[favid];
            delete this.markers[favid];
            delete this.categoryMarkers[cat][favid];
        }
        // category
        this.map.removeLayer(this.categoryLayers[cat]);
        delete this.categoryLayers[cat];
        delete this.categoryMarkers[cat];
        delete this.categoryDivIcon[cat];
        delete this.categoryColors[cat];
        $('#category-list #' + cat.replace(/\s+/g, '-') + '-category').fadeOut('slow', function() {
            $(this).remove();
        });

        if (updateSlider) {
            this.updateTimeFilterRange();
            this.timeFilterController.setSliderToMaxInterval();
        }
    },

    updateCategoryCounters: function() {
        var count;
        var total = 0;
        for (var cat in this.categoryMarkers) {
            count = Object.keys(this.categoryMarkers[cat]).length;
            $('#' + cat.replace(/\s+/g, '-')+'-category .app-navigation-entry-utils-counter').text(count);
            total = total + count;
        }
        //$('#navigation-favorites > .app-navigation-entry-utils .app-navigation-entry-utils-counter').text(total);
    },

    enterAddFavoriteMode: function(categoryName) {
        this.addFavoriteCategory = categoryName;
        $('.leaflet-container, .mapboxgl-map').css('cursor','crosshair');
        this.map.on('click', this.addFavoriteClickMap);
        this.map.leftClickLock = true;
        $('#addFavoriteButton button').removeClass('icon-add').addClass('icon-history');
        $('#explainaddpoint').show();
        this.addFavoriteMode = true;
        OC.Notification.showTemporary(t('maps', 'Click on the map to add a favorite, press ESC to cancel'));
    },

    leaveAddFavoriteMode: function() {
        $('.leaflet-container, .mapboxgl-map').css('cursor','grab');
        this.map.off('click', this.addFavoriteClickMap);
        this.map.leftClickLock = false;
        $('#addFavoriteButton button').addClass('icon-add').removeClass('icon-history');
        this.addFavoriteMode = false;
        this.addFavoriteCategory = null;
    },

    addFavoriteClickMap: function(e) {
        var categoryName = this.favoritesController.addFavoriteCategory;
        if (categoryName === this.favoritesController.defaultCategory && this.favoritesController.lastUsedCategory !== null) {
            categoryName = this.favoritesController.lastUsedCategory;
        }
        this.favoritesController.leaveAddFavoriteMode();
        this.favoritesController.addFavoriteDB(categoryName, e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6), null);
    },

    contextAddFavorite: function(e) {
        var categoryName = this.favoritesController.defaultCategory;
        if (this.favoritesController.lastUsedCategory !== null) {
            categoryName = this.favoritesController.lastUsedCategory;
        }
        this.favoritesController.addFavoriteDB(categoryName, e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6), null);
    },

    // make the request
    addFavoriteDB: function(category, lat, lng, name, comment=null, extensions=null) {
        var that = this;
        $('#navigation-favorites').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        var req = {
            name: name,
            lat: lat,
            lng: lng,
            category: category,
            comment: comment,
            extensions: extensions
        };
        var url = generateUrl('/apps/maps/favorites');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.addFavoriteMap(response, true, true);
            that.updateCategoryCounters();
            // show edition popup
            console.log(response);
            that.openEditionPopup(response.id);
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to add favorite'));
        });
    },

    // add a marker to the corresponding layer
    addFavoriteMap: function(fav, enableCategory=false, fromUserAction=false) {
        // manage category first
        var cat = fav.category;
        if (!this.categoryLayers.hasOwnProperty(cat)) {
            this.addCategory(cat, enableCategory);
            if (enableCategory) {
                this.saveEnabledCategories();
            }
        }
        else {
            // if favorites are hidden, show them
            if (fromUserAction && !this.map.hasLayer(this.cluster)) {
                this.toggleFavorites();
                this.optionsController.saveOptionValues({favoritesEnabled: this.map.hasLayer(this.cluster)});
            }
            // if the category is disabled, enable it
            if (fromUserAction && !this.map.hasLayer(this.categoryLayers[cat])) {
                this.toggleCategory(cat);
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
        marker.on('contextmenu', this.favoriteMouseRightClick);

        // add to map and arrays
        this.favorites[fav.id] = fav;
        this.markers[fav.id] = marker;
        this.categoryMarkers[cat][fav.id] = marker;
        this.categoryLayers[cat].addLayer(marker);

        if (fromUserAction) {
            // we make sure created favorite is displayed
            var minFilter = this.timeFilterController.min;
            var maxFilter = this.timeFilterController.max;
            var startFilter = this.timeFilterController.valueBegin;
            var endFilter = this.timeFilterController.valueEnd;
            var favDate = fav.date_created;

            if (favDate < minFilter) {
                minFilter = favDate;
            }
            if (favDate < startFilter) {
                startFilter = favDate;
            }
            if (favDate > maxFilter) {
                maxFilter = favDate;
            }
            if (favDate > endFilter) {
                endFilter = favDate;
            }

            this.timeFilterController.updateSliderRange(minFilter, maxFilter);
            this.timeFilterController.setSlider(startFilter, endFilter);
            // and make sure slider will reset to correct values (dblclick)
            this.updateMyFirstLastDates();
        }
    },

    favoriteMouseover: function(e) {
        var favid = e.target.favid;
        var fav = this._map.favoritesController.favorites[favid];
        var cat = fav.category.replace(/\s+/g, '-');
        var favTooltip = this._map.favoritesController.getFavoriteTooltipContent(fav);
        e.target.bindTooltip(favTooltip, {
            className: 'leaflet-marker-favorite-tooltip tooltipfav-' + cat,
            direction: 'top'
        });
        e.target.openTooltip();
    },

    favoriteMouseout: function(e) {
        e.target.unbindTooltip();
        e.target.closeTooltip();
    },

    getFavoriteTooltipContent: function(fav) {
        var content = '<b>' + t('maps', 'Name') + ':</b> ' + (fav.name || t('maps', 'No name'));
        content = content + '<br/><b>' + t('maps', 'Category') + ':</b> ' + fav.category;
        if (fav.comment) {
            content = content + '<br/><b>' + t('maps', 'Comment') + ':</b> ' + fav.comment;
        }
        return content;
    },

    favoriteMouseClick: function(e) {
        var favid = e.target.favid;
        this._map.favoritesController.openEditionPopup(favid);
    },

    openEditionPopup: function(favid) {
        var that = this;
        var fav = this.favorites[favid];

        //e.target.unbindPopup();
        var popupContent = this.getFavoritePopupContent(fav);
        var popup = L.popup({
            closeOnClick: true,
            className: 'popovermenu open popupMarker',
            offset: L.point(-5, 9)
        })
            .setLatLng([fav.lat, fav.lng])
            .setContent(popupContent)
            .openOn(this.map);
        $(popup._closeButton).one('click', function(e){
            that.map.clickpopup = null;
        });
        // add completion to category field
        var catList = [];
        for (var c in this.categoryLayers) {
            catList.push(c);
        }
        $('input[role="category"]').autocomplete({
            source: catList
        });
        $('input[role="name"]').focus().select();
        this.map.clickpopup = true;
    },

    getFavoritePopupContent: function(fav) {
        var validText = t('maps', 'Submit');
        var deleteText = t('maps', 'Delete');
        var namePH = t('maps', 'Favorite name');
        var categoryPH = t('maps', 'Category');
        var commentPH = t('maps', 'Comment');
        var res =
            '<ul favid="' + fav.id + '">' +
            '   <li>' +
            '       <span class="menuitem">' +
            '           <span class="icon icon-favorite"></span>' +
            '           <form>' +
            '                <input role="name" type="text" value="' + (fav.name || '') + '" placeholder="' + namePH + '"/>' +
            '           </form>' +
            '       </span>' +
            '   </li>' +
            '   <li>' +
            '       <span class="menuitem">' +
            '           <span class="icon icon-category-organization"></span>' +
            '           <form>' +
            '                <input role="category" type="text" value="' + (fav.category || '') + '" placeholder="' + categoryPH + '"/>' +
            '           </form>' +
            '       </span>' +
            '   </li>' +
            '   <li>' +
            '       <span class="menuitem">' +
            '           <span class="icon icon-comment"></span>' +
            '           <form>' +
            '                <textarea role="comment" placeholder="' + commentPH + '" rows="1">' + (fav.comment || '') + '</textarea>' +
            '           </form>' +
            '       </span>' +
            '   </li>' +
            '   <li>' +
            '       <button class="icon-checkmark valideditfavorite">' +
            '           <span>' + validText + '</span>' +
            '       </button>' +
            '   </li>' +
            '   <li>' +
            '       <button class="icon-delete valideditdeletefavorite">' +
            '           <span>' + deleteText + '</span>' +
            '       </button>' +
            '   </li>' +
            '</ul>';
        return res;
    },

    favoriteMouseRightClick: function(e) {
        var that = this;
        var favid = e.target.favid;
        var fav = this._map.favoritesController.favorites[favid];
        this._map.clickpopup = true;

        e.target.unbindPopup();
        var popupContent = this._map.favoritesController.getFavoriteContextPopupContent(fav);

        var popup = L.popup({
            closeOnClick: true,
            className: 'popovermenu open popupMarker',
            offset: L.point(-5, 9)
        })
            .setLatLng([fav.lat, fav.lng])
            .setContent(popupContent)
            .openOn(this._map);
        $(popup._closeButton).one('click', function (e) {
            that._map.clickpopup = null;
        });
    },

    getFavoriteContextPopupContent: function(fav) {
        var moveText = t('maps', 'Move');
        var deleteText = t('maps', 'Delete');
        var res =
            '<ul favid="' + fav.id + '">' +
            '   <li>' +
            '       <button class="icon-link movefavorite">' +
            '           <span>' + moveText + '</span>' +
            '       </button>' +
            '   </li>' +
            '   <li>' +
            '       <button class="icon-delete deletefavorite">' +
            '           <span>' + deleteText + '</span>' +
            '       </button>' +
            '   </li>' +
            '</ul>';
        return res;
    },

    deleteFavoriteDB: function(favid) {
        var that = this;
        $('#navigation-favorites').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        var req = {
        };
        var url = generateUrl('/apps/maps/favorites/'+favid);
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
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to delete favorite'));
        });
    },

    deleteFavoriteMap: function(favid) {
        var marker = this.markers[favid];
        var fav = this.favorites[favid];
        var cat = fav.category;
        this.categoryLayers[cat].removeLayer(marker);

        delete this.categoryMarkers[cat][favid];
        delete this.markers[favid];
        delete this.favorites[favid];

        // delete category if empty
        if (Object.keys(this.categoryMarkers[cat]).length === 0) {
            this.deleteCategoryMap(cat, true);
            this.saveEnabledCategories();
        }

        // as this was triggered by user action :
        this.updateMyFirstLastDates();
    },

    editFavoriteFromPopup: function(button) {
        var ul = button.parent().parent();
        var favid = parseInt(ul.attr('favid'));
        var fav = this.favorites[favid];

        var newName = ul.find('input[role=name]').val();
        var newCategory = ul.find('input[role=category]').val() || this.defaultCategory;
        var newComment = ul.find('textarea[role=comment]').val();

        this.lastUsedCategory = newCategory;

        this.editFavoriteDB(favid, newName, newComment, newCategory, null, null);
    },

    editFavoriteDB: function(favid, name, comment, category, lat, lng) {
        var that = this;
        $('#navigation-favorites').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
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
        var url = generateUrl('/apps/maps/favorites/'+favid);
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
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
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
            var oldCategory = this.favorites[favid].category;
            var newCategory = category;
            if (newCategory !== oldCategory) {
                var marker = this.markers[favid];

                delete this.categoryMarkers[oldCategory][favid];
                this.categoryLayers[oldCategory].removeLayer(marker);

                var shouldSaveCategories = false;
                // delete old category if empty
                if (Object.keys(this.categoryMarkers[oldCategory]).length === 0) {
                    this.deleteCategoryMap(oldCategory, true);
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
                this.categoryMarkers[newCategory][favid] = marker;
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
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'crosshair');
        this.map.on('click', this.moveFavoriteClickMap);
        OC.Notification.showTemporary(t('maps', 'Click on the map to move the favorite, press ESC to cancel'));
    },

    leaveMoveFavoriteMode: function() {
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        this.map.off('click', this.moveFavoriteClickMap);
        this.movingFavoriteId = null;
    },

    moveFavoriteClickMap: function(e) {
        var lat = e.latlng.lat;
        var lng = e.latlng.lng;
        var favid = this.favoritesController.movingFavoriteId;
        var name = this.favoritesController.favorites[favid].name;
        this.favoritesController.leaveMoveFavoriteMode();
        this.favoritesController.editFavoriteDB(favid, name, null, null, lat, lng);
    },

    exportCategory: function(cat) {
        var catList = [cat];
        this.exportDisplayedFavorites(catList);
    },

    exportDisplayedFavorites: function(catList=null) {
        $('#navigation-favorites').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        if (catList === null) {
            catList = [];
            if (this.map.hasLayer(this.cluster)) {
                for (var cat in this.categoryLayers) {
                    if (this.map.hasLayer(this.categoryLayers[cat])) {
                        catList.push(cat);
                    }
                }
            }
        }
        var begin = this.timeFilterController.valueBegin;
        var end = this.timeFilterController.valueEnd;
        var req = {
            categoryList: catList,
            begin: begin,
            end: end
        };
        var url = generateUrl('/apps/maps/export/favorites');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            OC.Notification.showTemporary(t('maps', 'Favorites exported in {path}', {path: response}));
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        }).fail(function(response) {
            OC.Notification.showTemporary(t('maps', 'Failed to export favorites') + ': ' + response.responseText);
        });
    },

    importFavorites: function(path) {
        $('#navigation-favorites').addClass('icon-loading-small');
        $('.leaflet-container, .mapboxgl-map').css('cursor', 'wait');
        var that = this;
        var req = {
            path: path
        };
        var url = generateUrl('/apps/maps/import/favorites');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            OC.Notification.showTemporary(t('maps', '{nb} favorites imported from {path}', {nb: response.nbImported, path: path}));
            var catToDel = [];
            for (var cat in that.categoryLayers) {
                catToDel.push(cat);
            }
            for (var i=0; i < catToDel.length; i++) {
                that.deleteCategoryMap(catToDel[i]);
            }
            that.getFavorites();
            if (response.linesFound === true) {
                OC.Notification.showTemporary(
                    t('maps', 'Warning: tracks or routes were found in imported files, they were ignored.'));
            }
        }).always(function (response) {
            $('#navigation-favorites').removeClass('icon-loading-small');
            $('.leaflet-container, .mapboxgl-map').css('cursor', 'grab');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to import favorites'));
        });
    },

    getAutocompData: function() {
        var that = this;
        var fav, layer;
        var data = [];
        if (that.map.hasLayer(that.cluster)) {
            for (var cat in this.categoryLayers) {
                layer = this.categoryLayers[cat];
                if (this.map.hasLayer(layer)) {
                    layer.eachLayer(function (l) {
                        fav = that.favorites[l.favid];
                        if (fav.name) {
                            data.push({
                                type: 'favorite',
                                label: fav.name,
                                value: fav.name,
                                lat: fav.lat,
                                lng: fav.lng
                            });
                        }
                    });
                }
            }
        }
        return data;
    },

}

export default FavoritesController;
