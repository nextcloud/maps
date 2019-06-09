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
    <h3><?php p($l->t('OSRM settings')); ?> </h3>
    <label><?php p($l->t('...')); ?></label><br/>
    <br />
    <div id="osrm">
        <input id="osrmDEMO" type="checkbox" class="checkbox" <?php p($osrmDemoChecked); ?>/>
        <label for="osrmDEMO"><?php p($l->t('Show OSRM demo server')); ?></label>
        <br/>

        <label for="osrmURL"><?php p($l->t('OSRM server URL')); ?></label><br/>
        <input id="osrmURL" type="text" value="<?php p($_['osrmURL']); ?>"/><br/>
        <label for="osrmAPIKEY"><?php p($l->t('OSRM API key')); ?></label><br/>
        <input id="osrmAPIKEY" type="text" value="<?php p($_['osrmAPIKEY']); ?>"/>
    </div><br/>

    <h3><?php p($l->t('GraphHopper settings')); ?> </h3>
    <label><?php p($l->t('...')); ?></label><br/>
    <br />
    <div id="graphhopper">
        <label for="graphhopperURL"><?php p($l->t('GraphHopper server URL')); ?></label><br/>
        <input id="graphhopperURL" type="text" value="<?php p($_['graphhopperURL']); ?>"/><br/>
        <label for="graphhopperAPIKEY"><?php p($l->t('GraphHopper API key')); ?></label><br/>
        <input id="graphhopperAPIKEY" type="text" value="<?php p($_['graphhopperAPIKEY']); ?>"/>
    </div><br/>
</div>
