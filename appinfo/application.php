<?php
/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>, Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @copyright Sander Brand 2014, Vinzenz Rosenkranz 2016, 2017
 */

namespace OCA\Maps\AppInfo;


use OC\AppFramework\Utility\SimpleContainer;
use OCA\Maps\Service\AddressService;
use \OCP\AppFramework\App;
use OCA\Maps\Controller\PageController;
use OCA\Maps\Controller\UtilsController;
use OCA\Maps\Controller\FavoritesController;
use OCA\Maps\Controller\FavoritesApiController;
use OCA\Maps\Controller\DevicesController;
use OCA\Maps\Controller\DevicesApiController;
use OCA\Maps\Controller\RoutingController;
use OCA\Maps\Controller\TracksController;
use OCA\Maps\Hooks\FileHooks;
use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\FavoritesService;
use OCA\Maps\Service\DevicesService;
use OCA\Maps\Service\TracksService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


class Application extends App {
    public function __construct (array $urlParams=array()) {
        parent::__construct('maps', $urlParams);

        $container = $this->getContainer();

        $this->getContainer()->registerService('FileHooks', function($c) {
            return new FileHooks(
                $c->query('ServerContainer')->getRootFolder(),
                \OC::$server->query(PhotofilesService::class),
                \OC::$server->query(TracksService::class),
                $c->query('ServerContainer')->getLogger(),
                $c->query('AppName'),
                $c->query('ServerContainer')->getLockingProvider()
            );
        });

        $this->getContainer()->query('FileHooks')->register();

        $container->registerService(
            'FavoritesController', function ($c) {
                return new FavoritesController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig(),
                    $c->getServer()->getShareManager(),
                    $c->getServer()->getAppManager(),
                    $c->getServer()->getUserManager(),
                    $c->getServer()->getGroupManager(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $c->query('ServerContainer')->getLogger(),
                    new FavoritesService(
                        $c->query('ServerContainer')->getLogger(),
                        $c->query('ServerContainer')->getL10N($c->query('AppName'))
                    ),
                    $c->query('ServerContainer')->getDateTimeZone()
                );
            }
        );

        $container->registerService(
            'FavoritesApiController', function ($c) {
                return new FavoritesApiController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig(),
                    $c->getServer()->getShareManager(),
                    $c->getServer()->getAppManager(),
                    $c->getServer()->getUserManager(),
                    $c->getServer()->getGroupManager(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $c->query('ServerContainer')->getLogger(),
                    new FavoritesService(
                        $c->query('ServerContainer')->getLogger(),
                        $c->query('ServerContainer')->getL10N($c->query('AppName'))
                    )
                );
            }
        );

        $container->registerService(
            'DevicesController', function ($c) {
                return new DevicesController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig(),
                    $c->getServer()->getShareManager(),
                    $c->getServer()->getAppManager(),
                    $c->getServer()->getUserManager(),
                    $c->getServer()->getGroupManager(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $c->query('ServerContainer')->getLogger(),
                    new DevicesService(
                        $c->query('ServerContainer')->getLogger(),
                        $c->query('ServerContainer')->getL10N($c->query('AppName'))
                    ),
                    $c->query('ServerContainer')->getDateTimeZone()
                );
            }
        );

        $container->registerService(
            'DevicesApiController', function ($c) {
                return new DevicesApiController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig(),
                    $c->getServer()->getShareManager(),
                    $c->getServer()->getAppManager(),
                    $c->getServer()->getUserManager(),
                    $c->getServer()->getGroupManager(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $c->query('ServerContainer')->getLogger(),
                    new DevicesService(
                        $c->query('ServerContainer')->getLogger(),
                        $c->query('ServerContainer')->getL10N($c->query('AppName'))
                    )
                );
            }
        );

        $container->registerService(
            'RoutingController', function ($c) {
                return new RoutingController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig(),
                    $c->getServer()->getShareManager(),
                    $c->getServer()->getAppManager(),
                    $c->getServer()->getUserManager(),
                    $c->getServer()->getGroupManager(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $c->query('ServerContainer')->getLogger(),
                    $c->query('ServerContainer')->getDateTimeZone()
                );
            }
        );

        $container->registerService(
            'TracksController', function ($c) {
                return new TracksController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig(),
                    $c->getServer()->getShareManager(),
                    $c->getServer()->getAppManager(),
                    $c->getServer()->getUserManager(),
                    $c->getServer()->getGroupManager(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $c->query('ServerContainer')->getLogger(),
                    new TracksService(
                        $c->query('ServerContainer')->getLogger(),
                        $c->query('ServerContainer')->getL10N($c->query('AppName')),
                        $c->query('ServerContainer')->getRootFolder(),
                        $c->getServer()->getShareManager()
                    )
                );
            }
        );

        $container->registerService(
            'UtilsController', function ($c) {
                return new UtilsController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig(),
                    $c->getServer()->getAppManager()
                );
            }
        );

        $this->registerFeaturePolicy();
    }

	private function registerFeaturePolicy() {
		/** @var EventDispatcherInterface $dispatcher */
		$dispatcher = $this->getContainer()->getServer()->getEventDispatcher();

		$dispatcher->addListener('OCP\Security\FeaturePolicy\AddFeaturePolicyEvent', function (\OCP\Security\FeaturePolicy\AddFeaturePolicyEvent $e) {
			$fp = new \OCP\AppFramework\Http\EmptyFeaturePolicy();
			$fp->addAllowedGeoLocationDomain('\'self\'');
			$e->addPolicy($fp);
		});
	}

}
