<?php

use OCP\Util;

$appId = OCA\Maps\AppInfo\Application::APP_ID;

Util::addScript($appId, $appId . '-main');
Util::addStyle($appId, $appId . '-main');
Util::addScript($appId, $appId . '-report-error-map-action');
Util::addStyle($appId, $appId . '-report-error-map-action');
