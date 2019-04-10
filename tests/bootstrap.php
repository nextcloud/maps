<?php
/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 */
define('PHPUNIT_RUN', 1);

// ugly hack to fix issues with template code using static code
$_SERVER['REQUEST_URI'] = '/index.php/apps/maps/';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require_once __DIR__.'/../../../lib/base.php';

if (version_compare(implode('.', \OCP\Util::getVersion()), '8.2', '>=')) {
    \OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests');
    \OC_App::loadApp('maps');
}

//if(!class_exists('PHPUnit_Framework_TestCase')) {
//  require_once('PHPUnit/Autoload.php');
//}

OC_Hook::clear();
