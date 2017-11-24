<?php
/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>
 * @copyright Sander Brand 2014
 */

namespace OCA\Maps\AppInfo;

$l = \OC::$server->getL10N('maps');

\OC::$server->getNavigationManager()->add(array(
    // the string under which your app will be referenced in owncloud
    'id' => 'maps',

    // sorting weight for the navigation. The higher the number, the higher
    // will it be listed in the navigation
    'order' => 10,

    // the route that will be shown on startup
    'href' => \OC::$server->getURLGenerator()->linkToRoute('maps.page.index'),

    // the icon that will be shown in the navigation
    // this file needs to exist in img/
    'icon' => \OC::$server->getURLGenerator()->imagePath('maps', 'maps.svg'),

    // the title of your application. This will be used in the
    // navigation or on the settings page of your app
    'name' => $l->t('Maps')
));
