<?php
/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>, Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @copyright Sander Brand 2014, Vinzenz Rosenkranz 2016, 2017
 */

namespace OCA\Maps\AppInfo;

use OCP\AppFramework\App;
use OCA\Maps\Service\AddressService;
use OCP\Util;
use Symfony\Component\EventDispatcher\GenericEvent;
use OCA\Maps\Hooks\FileHooks;

$app = new Application();
$container = $app->getContainer();

$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function() {
    Util::addScript('maps', 'filetypes');
    Util::addStyle('maps', 'filetypes');
});

// carddav/caldav lookup addresses
$listener = function($event) use ($container) {
    if ($event instanceof GenericEvent) {
        $cData = $event->getArgument('cardData');
        $cUri = $event->getArgument('cardUri');
        $a = $container->query(AddressService::class);
        $a->scheduleVCardForLookup($cData, $cUri);
    }
};
$deletionListener = function($event) use ($container) {
    if ($event instanceof GenericEvent) {
        $cUri = $event->getArgument('cardUri');
        $a = $container->query(AddressService::class);
        $a->deleteDBContactAddresses($cUri);
    }
};

$eventDispatcher->addListener('\OCA\DAV\CardDAV\CardDavBackend::createCard', $listener);
$eventDispatcher->addListener('\OCA\DAV\CardDAV\CardDavBackend::updateCard', $listener);
$eventDispatcher->addListener('\OCA\DAV\CardDAV\CardDavBackend::deleteCard', $deletionListener);

$l = \OC::$server->getL10N('maps');

$container->query('OCP\INavigationManager')->add(function () use ($container) {
    $urlGenerator = $container->query('OCP\IURLGenerator');
    $l10n = $container->query('OCP\IL10N');
    return [
        'id' => 'maps',

        'order' => 10,

        // the route that will be shown on startup
        'href' => $urlGenerator->linkToRoute('maps.page.index'),

        // the icon that will be shown in the navigation
        // this file needs to exist in img/
        'icon' => $urlGenerator->imagePath('maps', 'maps.svg'),

        // the title of your application. This will be used in the
        // navigation or on the settings page of your app
        'name' => $l10n->t('Maps'),
    ];
});
