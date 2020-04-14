<?php
OCP\Util::addscript('maps', 'adminSettings');
OCP\Util::addstyle('maps', 'adminSettings');
$osrmDemoChecked = '';
if (!isset($_['osrmDEMO']) || $_['osrmDEMO'] === '1') {
    $osrmDemoChecked = 'checked="checked"';
}
?>

<div class="section" id="routing">

</div>
