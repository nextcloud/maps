import { generateUrl } from "@nextcloud/router";

(function() {
    if (!OCA.Maps) {
        OCA.Maps = {};
    }
})();

function setMapsRoutingSettings(key, value) {
    var values = {};
    values[key] = value;
    var url = generateUrl('/apps/maps/setRoutingSettings');
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
    $('body').on('change',
        'input#osrmFootURL, ' +
        'input#osrmCarURL, ' +
        'input#osrmBikeURL, ' +
        'input#graphhopperURL, ' +
        'input#graphhopperAPIKEY, ' +
        '#osrmDEMO, ' +
        'input#mapboxAPIKEY', function(e) {
        var value = $(this).val();
        setMapsRoutingSettings($(this).attr('id'), value);
    });
    $('body').on('change', '#osrmDEMO', function(e) {
        var value = $(this).is(':checked') ? '1' : '0';
        setMapsRoutingSettings($(this).attr('id'), value);
    });
});
