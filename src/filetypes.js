import { generateUrl } from '@nextcloud/router';

$(document).ready(function() {

    if (OCA.Files && OCA.Files.fileActions) {

        function openFile(file, data) {
            var token = $('#sharingToken').val();
            // if we are logged
            if (!token) {
                var dir = (data.dir === '/') ? '' : data.dir;
                var url = generateUrl('apps/maps/?track={dir}%2F{file}',{'dir': dir, 'file': file});
            }
            window.open(url, '_blank');
        }

        function importFavoritesFile(file, data) {
            $('#content').css('cursor', 'wait');
            var dir = (data.dir === '/') ? '' : data.dir;
            var path = dir + '/' + file;
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
                OC.Notification.showTemporary(t('maps', '{nb} favorites imported from {path}', {nb: response, path: path}));
            }).always(function (response) {
                $('#content').css('cursor', 'default');
            }).fail(function() {
                OC.Notification.showTemporary(t('maps', 'Failed to import favorites'));
            });
        }

        function importDevicesFile(file, data) {
            $('#content').css('cursor', 'wait');
            var dir = (data.dir === '/') ? '' : data.dir;
            var path = dir + '/' + file;
            var req = {
                path: path
            };
            var url = generateUrl('/apps/maps/import/devices');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                OC.Notification.showTemporary(t('maps', '{nb} devices imported from {path}', {nb: response, path: path}));
            }).always(function (response) {
                $('#content').css('cursor', 'default');
            }).fail(function(response) {
                OC.Notification.showTemporary(t('maps', 'Failed to import devices') + ': ' + response.responseText);
            });
        }

        // default action is set only for logged in users
        if (!$('#sharingToken').val()){

            OCA.Files.fileActions.registerAction({
                name: 'viewTrackMaps',
                displayName: t('maps', 'View in Maps'),
                mime: 'application/gpx+xml',
                permissions: OC.PERMISSION_READ,
                iconClass: 'icon-maps-black',
                actionHandler: openFile
            });

            OCA.Files.fileActions.register('application/gpx+xml', 'viewTrackMapsDefault', OC.PERMISSION_READ, '', openFile);
            OCA.Files.fileActions.setDefault('application/gpx+xml', 'viewTrackMapsDefault');

            // import gpx files as favorites
            OCA.Files.fileActions.registerAction({
                name: 'importGpxFavoritesMaps',
                displayName: t('maps', 'Import as favorites in Maps'),
                mime: 'application/gpx+xml',
                permissions: OC.PERMISSION_READ,
                iconClass: 'icon-maps-black',
                actionHandler: importFavoritesFile
            });
            // import kmz files as favorites
            OCA.Files.fileActions.registerAction({
                name: 'importKmzFavoritesMaps',
                displayName: t('maps', 'Import as favorites in Maps'),
                mime: 'application/vnd.google-earth.kmz',
                permissions: OC.PERMISSION_READ,
                iconClass: 'icon-maps-black',
                actionHandler: importFavoritesFile
            });
            // import kml files as favorites
            OCA.Files.fileActions.registerAction({
                name: 'importKmlFavoritesMaps',
                displayName: t('maps', 'Import as favorites in Maps'),
                mime: 'application/vnd.google-earth.kml+xml',
                permissions: OC.PERMISSION_READ,
                iconClass: 'icon-maps-black',
                actionHandler: importFavoritesFile
            });

            // import gpx files as devices
            OCA.Files.fileActions.registerAction({
                name: 'importGpxDevicesMaps',
                displayName: t('maps', 'Import as devices in Maps'),
                mime: 'application/gpx+xml',
                permissions: OC.PERMISSION_READ,
                iconClass: 'icon-maps-black',
                actionHandler: importDevicesFile
            });
            // import kmz files as devices
            OCA.Files.fileActions.registerAction({
                name: 'importKmzDevicesMaps',
                displayName: t('maps', 'Import as devices in Maps'),
                mime: 'application/vnd.google-earth.kmz',
                permissions: OC.PERMISSION_READ,
                iconClass: 'icon-maps-black',
                actionHandler: importDevicesFile
            });
            // import kml files as devicess
            OCA.Files.fileActions.registerAction({
                name: 'importKmlDevicesMaps',
                displayName: t('maps', 'Import as devices in Maps'),
                mime: 'application/vnd.google-earth.kml+xml',
                permissions: OC.PERMISSION_READ,
                iconClass: 'icon-maps-black',
                actionHandler: importDevicesFile
            });
        }
    }

});

