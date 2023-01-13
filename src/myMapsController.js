import {generateUrl} from '@nextcloud/router';
import {hslToRgb, getLetterColor, Timer} from './utils';
import escapeHTML from 'escape-html';

function MyMapsController (optionsController, favoritesController, photosController, tracksController) {
    this.optionsController = optionsController;
    this.favoritesController = favoritesController;
    this.photosController = photosController;
    this.tracksController = tracksController;

    this.myMapsEnabled = false;
    this.myMapsList = [
        {
            name: t('maps',"Default Map"),
            id: null,
            path: t('maps',"Your Default Map"),
            color: "#098bd1"
        }
    ];
    this.myMapsListLoaded = false;
    this.changingColorOf = null;
    this.myMapsColors = {};
    this.myMapDeletionTimer = {};
    this.defaultMyMapName = t('maps', "New Map");
}

MyMapsController.prototype = {

    // set up favorites-related UI stuff
    initController: function (map) {
        this.map = map;
        this.mainLayer = L.featureGroup();
        var that = this;
        var body = $('body');
        var n = $('#navigation-my-maps');
        // toggle my-maps
        body.on('click', '#navigation-my-maps > a', function (e) {
            that.toggleMyMaps();
            that.optionsController.saveOptionValues({myMapsEnabled: that.myMapsEnabled});
            if (!n.hasClass('open')) {
                that.toggleMyMapsList();
                that.optionsController.saveOptionValues({myMapsListShow: n.hasClass('open')});
            }
        });
        // expand track list
        body.on('click', '#navigation-my-maps', function (e) {
            if (e.target.tagName === 'LI' && $(e.target).attr('id') === 'navigation-my-maps') {
                that.toggleMyMapsList();
                that.optionsController.saveOptionValues({myMapsListShow: n.hasClass('open')});
            }
        });
        body.on('click', '.my-maps-line .my-maps-name', function(e) {
            var id = $(this).parent().attr('map');
            that.openMyMap(id);
        });
        body.on('click', '#addMyMapButton, #add-map', function(e) {
            that.addMyMap();
        });
        //rename map
        body.on('click', '.renameMyMap', function(e) {
            $(this).parent().parent().parent().parent().find('.renameMapInput').focus().select();
            $('#category-list > li').removeClass('editing');
            $(this).parent().parent().parent().parent().addClass('editing');
        });
        body.on('click', '.renameMyMapOk', function(e) {
            var id = $(this).parent().parent().parent().attr('map');
            $(this).parent().parent().parent().removeClass('editing').addClass('icon-loading-small');
            var newName = $(this).parent().find('.renameMyMapInput').val() || that.defaultMyMapName
            that.renameMyMap(id, newName);
        });
        body.on('keyup', '.renameMyMapInput', function(e) {
            if (e.key === 'Enter') {
                var id = $(this).parent().parent().parent().attr('map');
                $(this).parent().parent().parent().removeClass('editing').addClass('icon-loading-small');
                var newName = $(this).parent().find('.renameMyMapInput').val() || that.defaultMyMapName
                that.renameMyMap(id, newName);
            }
            else if (e.key === 'Escape') {
                $(this).parent().parent().parent().removeClass('editing');
            }
        });
        body.on('click', '.renameMyMapClose', function(e) {
            $(this).parent().parent().parent().removeClass('editing');
        });
        //delete map
        body.on('click', '.deleteMyMap', function(e) {
            var id = $(this).parent().parent().parent().parent().attr('map');
            $(this).parent().parent().parent().parent().addClass('deleted');
            that.myMapDeletionTimer[id] = new Timer(function() {
                that.deleteMyMap(id);
            }, 7000);
        });
        body.on('click', '.undoDeleteMyMap', function(e) {
            var id = $(this).parent().parent().attr('map');
            $(this).parent().parent().removeClass('deleted');
            that.myMapDeletionTimer[id].pause();
            delete that.myMapDeletionTimer[id];
        });
        body.on('click', '.changeColorMyMap', function(e) {
            var id = $(this).parent().parent().parent().parent().attr('map');
            that.askChangeTrackColor(id);
            that.map.closePopup();
        });
        body.on('change', '#mymapscolorinput', function(e) {
            that.okColor();
        });
        body.on('click', '.shareMyMap', function(e) {
            var id = $(this).parent().attr('map');
            that.shareMyMap(id);
        });
    },

    // expand or fold my maps list in sidebar
    toggleMyMapsList: function () {
        $('#navigation-my-maps').toggleClass('open');
    },

    // toggle my maps general layer on map and save state in user options
    toggleMyMaps: function () {
        var that = this;
        var n = $('#navigation-my-maps');
        if (this.myMapsEnabled) {
            n.removeClass('active');
            $('#map').focus();
            this.myMapsEnabled = false;
        } else {
            if (!this.myMapsListLoaded) {
                this.getMyMaps();
            }
            n.addClass('open');
            n.addClass('active');
            this.myMapsEnabled = true;
        }
    },



    addMenuEntry: function (map) {
        var defaultMap = map.id === null;
        var name = map.name;
        var hsl = getLetterColor(name[0], name[1]);
        var path = map.path;
        var color = map.color ||  hslToRgb(hsl.h/360, hsl.s/100, hsl.l/100);
        this.myMapsColors[map.id] = color;
        var active = map.id==this.optionsController.myMapId;

        // side menu entry
        var imgurl = generateUrl('/svg/core/actions/timezone?color=' + color.replace('#', ''));
        var li = '<li class="my-maps-line" id="' + map.id + '-map" map="' + (map.id||"") + '" name="' + name + '" >' +
            '    <a href="#" class="my-maps-name" id="' + map.id + '-my-maps-name" title="' + escapeHTML(path) + '" style="background-image: url(' + imgurl + ')">' + name + '</a>' +
            (!defaultMap ?
            '    <div class="app-navigation-entry-utils">' +
            '        <ul>' +
            '            <li class="app-navigation-entry-utils-menu-button myMapMenuButton">' +
            '                <button></button>' +
            '            </li>' +
            '        </ul>' +
            '    </div>' +
            '    <div class="app-navigation-entry-menu">' +
            '        <ul>' +
            '            <li>' +
            '                <a href="#" class="renameMyMap">' +
            '                    <span class="icon-rename"></span>' +
            '                    <span>' + t('maps', 'Rename') + '</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="changeColorMyMap">' +
            '                    <span class="icon-rename"></span>' +
            '                    <span>' + t('maps', 'Change color') + '</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="shareMyMap">' +
            '                    <span class="icon-share"></span>' +
            '                    <span>' + t('maps', 'Share') + '</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="deleteMyMap">' +
            '                    <span class="icon-delete"></span>' +
            '                    <span>' + t('maps', 'Delete') + '</span>' +
            '                </a>' +
            '            </li>' +
            '        </ul>' +
            '    </div>' +
            '    <div class="app-navigation-entry-deleted">' +
            '        <div class="app-navigation-entry-deleted-description">'+t('maps', 'Map deleted')+'</div>' +
            '        <button class="app-navigation-entry-deleted-button icon-history undoDeleteMyMap" title="Undo"></button>' +
            '    </div>' +
            '    <div class="app-navigation-entry-edit">' +
            '        <div>' +
            '            <input type="text" value="'+name+'" class="renameMyMapInput">' +
            '            <input type="submit" value="" class="icon-close renameMyMapClose">' +
            '            <input type="submit" value="" class="icon-checkmark renameMyMapOk">' +
            '        </div>' +
            '    </div>' : "") +
            '</li>';

        var beforeThis = null;
        var that = this;

        var nameLower = name.toLowerCase();
        var myMapName;
        $('#my-maps-list > li').each(function () {
            myMapName = $(this).attr('name');
            if (nameLower.localeCompare(myMapName) < 0) {
                beforeThis = $(this);
                return false;
            }
        });
        if (beforeThis !== null) {
            $(li).insertBefore(beforeThis);
        } else {
            $('#my-maps-list').append(li);
        }
        if (active) {
            $('#'+map.id+'-map').addClass('active');
        }

    },

    askChangeTrackColor: function(id) {
        this.changingColorOf = id;
        var currentColor = this.myMapsColors[id];
        $('#mymapscolorinput').val(currentColor);
        $('#mymapscolorinput').click();
    },

    okColor: function() {
        var color = $('#mymapscolorinput').val();
        var id = this.changingColorOf;
        this.myMapsColors[id] = color;
        this.changeMyMapsColor(id, color);
    },

    changeMyMapsColor: function(id, color) {
        var that = this;
        $('#my-maps-list > li[map="'+id+'"]').addClass('icon-loading-small');
        var req = {
            values: {color: color}
        };
        var url = generateUrl('/apps/maps/maps/' + id);
        $.ajax({
            type: 'PUT',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            var imgurl = generateUrl('/svg/core/actions/timezone?color=' + color.replace('#', ''));
            $('#my-maps-list > li[map='+id+'] .my-maps-name').attr('style', 'background-image: url('+imgurl+')');
        }).always(function (response) {
            $('#my-maps-list > li[map="'+id+'"]').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(
                t('maps', 'Failed to save map color')
            );
        });
    },

    addMyMap: function(id) {
        var map = {
            name: "New Map",
            id: "new"
        };
        this.addMenuEntry(map);
        $('#my-maps-list > li').removeClass('editing');
        $('#new-map').find('.renameMapInput').focus().select();
        $('#new-map').addClass('editing');
    },

    replaceMapMenuEntry: function(id, map) {
        delete this.myMapDeletionTimer[id];
        delete this.myMapsColors[id];
        $('#' + id + '-map').remove();
        this.addMenuEntry(map)
    },

    deleteMyMap: function(id) {
        var that = this;
        $('#navigation-my-maps').addClass('icon-loading-small');
        var req = {
            id: id,
        };
        var url = generateUrl('/apps/maps/maps/' + id);
        $.ajax({
            type: 'DELETE',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('#' + id + '-map').fadeOut('slow', function() {
                $(this).remove();
            });
            delete that.myMapsColors[id];
//            delete that.myMapsList[id];
            delete that.myMapDeletionTimer[id];
        }).always(function (response) {
            $('#navigation-my-maps').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to delete map'));
        });
    },

    renameMyMap: function(id, newName) {
        var that = this;
        var isNewMap = id==="new";
        var req = {
            id: isNewMap ? null : id,
            values: {newName: newName},
        };
        var url = generateUrl('/apps/maps/maps' + (isNewMap ? '' : '/' + id));
        $.ajax({
            type: isNewMap? 'POST' : 'PUT',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            $('#map-list #' + id + '-map').removeClass('editing');
            that.replaceMapMenuEntry(id, response);
        }).always(function (response) {
            $('#map-list #' + id + '-map').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to rename map'));
        });
    },

    shareMyMap: function(id) {
        OC.Notification.showTemporary(t('maps', 'Share map not implemented yet, just go to files and share the folder.'));
    },

    getMyMaps: function () {
        var that = this;
        $('#navigation-my-maps').addClass('icon-loading-small');
        var req = {};
        var url = generateUrl('/apps/maps/maps');
        $.ajax({
            type: 'GET',
            url: url,
            data: req,
            async: true
        }).done(function (response) {
            that.myMapsList.push.apply(that.myMapsList,response);
            $('#navigation-my-maps').removeClass('icon-loading-small');
            that.myMapsList.forEach(function (map) {
                that.addMenuEntry(map)
            })
            that.myMapsListLoaded = true;
        }).always(function (response) {
            $('#navigation-my-maps').removeClass('icon-loading-small');
        }).fail(function() {
            OC.Notification.showTemporary(t('maps', 'Failed to load your maps'));
        });
    },

    openMyMap: function (id) {
        if (id !== "") {
            window.open(generateUrl('/apps/maps/m/'+id));
        } else {
            window.open(generateUrl('/apps/maps/'));
        }
    },

}

export default MyMapsController;
