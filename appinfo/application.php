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


use OCA\Maps\Controller\PhotosController;
use OCA\Maps\DB\FavoriteShareMapper;
use OCA\Maps\Controller\PublicFavoritesApiController;
use OCA\Maps\DB\GeophotoMapper;
use OCA\Maps\Service\GeophotoService;
use OCA\Maps\Controller\UtilsController;
use OCA\Maps\Controller\FavoritesController;
use OCA\Maps\Controller\FavoritesApiController;
use OCA\Maps\Controller\DevicesController;
use OCA\Maps\Controller\PublicPageController;
use OCA\Maps\Controller\DevicesApiController;
use OCA\Maps\Controller\RoutingController;
use OCA\Maps\Controller\TracksController;
use OCA\Maps\Hooks\FileHooks;
use OCA\Maps\Service\MyMapsService;
use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\FavoritesService;
use OCA\Maps\Service\DevicesService;
use OCA\Maps\Service\TracksService;
use \OCP\AppFramework\App;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IPreview;
use OCP\Share\IManager;
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
                        $c->query('ServerContainer')->getL10N($c->query('AppName')),
                        $c->query('ServerContainer')->getSecureRandom()
                    ),
                    $c->query('ServerContainer')->getDateTimeZone(),
                  new FavoriteShareMapper(
                    $c->query('DatabaseConnection'),
                    $c->query('ServerContainer')->getSecureRandom()
                  )
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
                        $c->query('ServerContainer')->getL10N($c->query('AppName')),
                        $c->query('ServerContainer')->getSecureRandom()
                    )
                );
            }
        );

        $container->registerService(
            'GeophotoMapper', function ($c) {
                return new GeophotoMapper(
                    $c->query(IDBConnection::class)
                );
            }
        );

        $container->registerService(
            'GeophotoService', function ($c) {
                return new GeophotoService(
                    $c->query('ServerContainer')->getLogger(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $c->query('GeophotoMapper'),
                    $c->query(IPreview::class),
                    $c->query('TracksService'),
                    $c->query('DevicesService'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId'))
                );
            }
        );

        $container->registerService(
            PhotosController::class, function ($c) {
                return new PhotosController(
                    $c->query('AppName'),
                    $c->query('ServerContainer')->getLogger(),
                    $c->query('Request'),
                    $c->query('GeophotoService'),
                    $c->query(PhotofilesService::class),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId'))
                );
            }
        );

        $container->registerService(
            PhotofilesService::class, function ($c) {
            return new PhotofilesService(
                $c->query('ServerContainer')->getLogger(),
                $c->query('ServerContainer')->getRootFolder(),
                $c->query('ServerContainer')->getL10N($c->query('AppName')),
                $c->query(GeophotoMapper::class),
                $c->query(IManager::class),
                $c->query(IJobList::class)
            );
        }
        );

        $container->registerService(
            'PublicFavoritesAPIController', function ($c) {
                return new PublicFavoritesApiController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('Session'),
                    new FavoritesService(
                        $c->query('ServerContainer')->getLogger(),
                        $c->query('ServerContainer')->getL10N($c->query('AppName')),
                        $c->query('ServerContainer')->getSecureRandom()
                    ),
                  new FavoriteShareMapper(
                    $c->query('DatabaseConnection'),
                    $c->query('ServerContainer')->getSecureRandom()
                  )
                );
            }
        );

        $container->registerService(
            'PublicPageController', function ($c) {
                return new PublicPageController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('Session'),
                    $c->query('ServerContainer')->getConfig(),
                    $c->query('Logger'),
                    new FavoriteShareMapper(
                        $c->query('DatabaseConnection'),
                        $c->query('ServerContainer')->getSecureRandom()
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
            'DevicesService', function ($c) {
                return new DevicesService(
                    $c->query('ServerContainer')->getLogger(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName'))
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
                    $c->query('DevicesService')
                );
            }
        );

        $container->registerService(
            MyMapsService::class, function ($c) {
            return new MyMapsService(
                $c->query('ServerContainer')->getLogger(),
                $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                $c->query('UserId')
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
            'TracksService', function ($c) {
                return new TracksService(
                    $c->query('ServerContainer')->getLogger(),
                    $c->query('ServerContainer')->getL10N($c->query('AppName')),
                    $c->query('ServerContainer')->getRootFolder(),
                    $c->getServer()->getShareManager()
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
                    $c->query('TracksService')
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
