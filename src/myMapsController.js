import {generateUrl} from '@nextcloud/router';
import {hslToRgb,getLetterColor} from './utils';

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
        body.on('click', '.my-maps-item .my-maps-name', function(e) {
            var id = $(this).parent().attr('map');
            that.openMyMap(id);
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
        var name = map.name;
        var hsl = getLetterColor(name[0], name[1]);
        var path = map.path;
        var color = map.color ||  hslToRgb(hsl.h/360, hsl.s/100, hsl.l/100);

        // side menu entry
        var imgurl = generateUrl('/svg/core/actions/timezone?color=' + color.replace('#', ''));
        var li = '<li class="my-maps-item" id="' + name + '" map="' + (map.id||"") + '" name="' + name + '">' +
            '    <a href="#" class="my-maps-name" id="' + name + '-my-maps-name" title="' + escapeHTML(path) + '" style="background-image: url(' + imgurl + ')">' + name + '</a>' +
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
            '                    <span>' + t('maps', 'rename') + '</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="zoomMyMapButton">' +
            '                    <span class="icon-search"></span>' +
            '                    <span>' + t('maps', 'Zoom to bounds') + '</span>' +
            '                </a>' +
            '            </li>' +
            '            <li>' +
            '                <a href="#" class="shareMyMap">' +
            '                    <span class="icon-share"></span>' +
            '                    <span>' + t('maps', 'Share') + '</span>' +
            '                </a>' +
            '            </li>' +
            '        </ul>' +
            '    </div>' +
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
