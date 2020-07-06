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


use OCA\Maps\DB\FavoriteShareMapper;
use \OCP\AppFramework\App;
use OCA\Maps\Hooks\FileHooks;
use OCA\Maps\Service\PhotofilesService;
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
