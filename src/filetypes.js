import { generateUrl } from '@nextcloud/router';
import { showSuccess, showError } from "@nextcloud/dialogs";

$(document).ready(function() {

    if (OCA.Files && OCA.Files.fileActions) {

		function openMap(file, data) {
			var mapId = data.fileList.dirInfo.id;
			var url = generateUrl('apps/maps/m/{mapId}',{mapId});

			window.open(url, '_blank');
		}

        function openTrackFile(file, data) {
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
                showSuccess(t('maps', '{nb} favorites imported from {path}', {nb: response, path: path}));
            }).always(function (response) {
                $('#content').css('cursor', 'default');
            }).fail(function() {
                showError(t('maps', 'Failed to import favorites'));
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
                showSuccess(t('maps', '{nb} devices imported from {path}', {nb: response, path: path}));
            }).always(function (response) {
                $('#content').css('cursor', 'default');
            }).fail(function(response) {
                showError(t('maps', 'Failed to import devices') + ': ' + response.responseText);
            });
        }

        // default action is set only for logged in users
        if (!$('#sharingToken').val()){
			//Open in Maps
			OCA.Files.fileActions.registerAction({
				name: 'viewMap',
				displayName: t('maps', 'View in Maps'),
				mime: 'application/x-nextcloud-maps',
				permissions: OC.PERMISSION_READ,
				iconClass: 'icon-maps-black',
				actionHandler: openMap
			});
			OCA.Files.fileActions.setDefault('application/x-nextcloud-maps', 'viewMap');

            OCA.Files.fileActions.registerAction({
                name: 'viewTrackMaps',
                displayName: t('maps', 'View in Maps'),
                mime: 'application/gpx+xml',
                permissions: OC.PERMISSION_READ,
                iconClass: 'icon-maps-black',
                actionHandler: openTrackFile
            });

            OCA.Files.fileActions.register('application/gpx+xml', 'viewTrackMapsDefault', OC.PERMISSION_READ, '', openTrackFile);
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
            // import geojson files as favorites
            OCA.Files.fileActions.registerAction({
                name: 'importGeoJsonFavoritesMaps',
                displayName: t('maps', 'Import as favorites in Maps'),
                mime: 'application/geo+json',
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

