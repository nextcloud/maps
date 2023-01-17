
<?php
$appId = OCA\Maps\AppInfo\Application::APP_ID;
script($appId, $appId . '-main');
?>
<input type="hidden" name="sharingToken" value="<?php p($_['sharingToken']) ?>" id="sharingToken">
