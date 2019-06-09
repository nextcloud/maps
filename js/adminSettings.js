(function() {
    if (!OCA.Maps) {
        OCA.Maps = {};
    }
})();

function setMapsRoutingSettings() {
    var values = {
        osrmDEMO: $('#osrmDEMO').is(':checked') ? '1' : '0',
        osrmURL: $('#osrmURL').val(),
        osrmAPIKEY: $('#osrmAPIKEY').val(),
        graphhopperURL: $('#graphhopperURL').val(),
        graphhopperAPIKEY: $('#graphhopperAPIKEY').val(),
    }
    var url = OC.generateUrl('/apps/maps/setRoutingSettings');
    var req = {
        values: values
    }
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function (response) {
        OC.Notification.showTemporary(
            t('maps', 'Settings were successfully saved')
        );
    }).fail(function() {
        OC.Notification.showTemporary(
            t('maps', 'Failed to save settings')
        );
    });
}

$(document).ready(function() {
    $('body').on('change', 'input#osrmURL, input#osrmAPIKEY, input#graphhopperURL, input#graphhopperAPIKEY, #osrmDEMO', function(e) {
        setMapsRoutingSettings();
    });
});
