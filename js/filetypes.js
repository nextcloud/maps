$(document).ready(function() {

    if (OCA.Files && OCA.Files.fileActions) {

        function openFile(file, data){
            var token = $('#sharingToken').val();
            // if we are logged
            if (!token) {
                var dir = (data.dir === '/') ? '' : data.dir;
                var url = OC.generateUrl('apps/maps/?track={dir}%2F{file}',{'dir': dir, 'file': file});
            }
            window.open(url, '_blank');
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
        }
    }

});

