<?php
OCP\Util::addscript('maps', 'adminSettings');
OCP\Util::addstyle('maps', 'adminSettings');
$osrmDemoChecked = '';
if (!isset($_['osrmDEMO']) || $_['osrmDEMO'] === '1') {
    $osrmDemoChecked = 'checked="checked"';
}
?>

<div class="section" id="routing">
    <h2><?php p($l->t('Maps routing settings')); ?></h2>
    <p class="settings-hint"><?php p($l->t('To enable routing, you must set up a routing engine below.')) ?></p>
    <h3><a href="http://project-osrm.org/" title="<?php p($l->t('OSRM Website')) ?>" target="_blank"><?php p($l->t('OSRM settings')); ?></a></h3>
    <p><?php p($l->t('An OSRM server URL looks like this : https://my.osrm.server.org:5000/route/v1')); ?></p>
    <p><?php p($l->t('Leave URL fields empty to disable OSRM routing provider.')); ?></p><br/>
    <div id="osrm">
        <label for="osrmCarURL"><?php p($l->t('OSRM server URL (car profile)')); ?></label><br/>
        <input id="osrmCarURL" type="text" value="<?php if (isset($_['osrmCarURL'])) p($_['osrmCarURL']); ?>"/><br/>

        <label for="osrmBikeURL"><?php p($l->t('OSRM server URL (bicycle profile)')); ?></label><br/>
        <input id="osrmBikeURL" type="text" value="<?php if (isset($_['osrmBikeURL'])) p($_['osrmBikeURL']); ?>"/><br/>

        <label for="osrmFootURL"><?php p($l->t('OSRM server URL (foot profile)')); ?></label><br/>
        <input id="osrmFootURL" type="text" value="<?php if (isset($_['osrmFootURL'])) p($_['osrmFootURL']); ?>"/><br/>

        <br/>
        <input id="osrmDEMO" type="checkbox" class="checkbox" <?php p($osrmDemoChecked); ?>/>
        <label for="osrmDEMO"><?php p($l->t('Show OSRM demo server')); ?></label>
    </div><br/>

    <h3><a href="https://www.graphhopper.com/" title="<?php p($l->t('Graphhopper Website')) ?>" target="_blank"><?php p($l->t('GraphHopper settings')); ?></a></h3>
    <p><?php p($l->t('A GraphHopper server URL looks like this : https://my.graphhopper.server.org:8989/route')); ?></p><br />

    <div id="graphhopper">
        <label for="graphhopperURL"><?php p($l->t('GraphHopper server URL (will use main graphhopper server if empty)')); ?></label><br/>
        <input id="graphhopperURL" type="text" value="<?php if (isset($_['graphhopperURL'])) p($_['graphhopperURL']); ?>"/><br/>
        <label for="graphhopperAPIKEY"><?php p($l->t('GraphHopper API key (mandatory if main server used)')); ?></label><br/>
        <input id="graphhopperAPIKEY" type="text" value="<?php if (isset($_['graphhopperAPIKEY'])) p($_['graphhopperAPIKEY']); ?>"/>
    </div><br/>

    <h3><a href="https://www.mapbox.com/" title="<?php p($l->t('Mapbox Website')) ?>" target="_blank"><?php p($l->t('Mapbox settings')); ?></a></h3>
    <p><?php p($l->t('Set the API key to use Mapbox routing service.')); ?></p>
    <p><?php p($l->t('Leave empty to disable.')); ?></p><br/>
    <div id="mapbox">
        <label for="mapboxAPIKEY"><?php p($l->t('Mapbox API key')); ?></label><br/>
        <input id="mapboxAPIKEY" type="text" value="<?php if (isset($_['mapboxAPIKEY'])) p($_['mapboxAPIKEY']); ?>"/>
    </div><br/>
</div>
