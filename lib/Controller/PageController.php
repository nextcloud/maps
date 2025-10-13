<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @authorVinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @copyright Vinzenz Rosenkranz 2017
 */

namespace OCA\Maps\Controller;

use OCA\Files\Event\LoadSidebar;
use OCA\Maps\Service\MyMapsService;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private string $userId,
		private IEventDispatcher $eventDispatcher,
		private IConfig $config,
		private IInitialState $initialState,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return TemplateResponse
	 */
	public function index(): TemplateResponse {
		$this->eventDispatcher->dispatch(LoadSidebar::class, new LoadSidebar());
		$this->eventDispatcher->dispatch(LoadViewer::class, new LoadViewer());

		$params = ['user' => $this->userId];
		$this->initialState->provideInitialState('photos', $this->config->getAppValue('photos', 'enabled', 'no') === 'yes');
		$response = new TemplateResponse('maps', 'main', $params);

		$this->addCsp($response);

		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function indexMyMap(int $myMapId, MyMapsService $service): TemplateResponse|RedirectResponse {
		$map = $service->getMyMap($myMapId, $this->userId);
		if ($map !== null && $map['id'] !== $myMapId) {
			// Instead of the id of the map containing folder the '.index.maps' file id was passed so redirect
			// this happens if coming from the files app integration
			return new RedirectResponse(
				$this->urlGenerator->linkToRouteAbsolute('maps.page.indexMyMap', ['myMapId' => $map['id']]),
			);
		}

		$this->eventDispatcher->dispatch(LoadSidebar::class, new LoadSidebar());
		$this->eventDispatcher->dispatch(LoadViewer::class, new LoadViewer());

		$params = ['user' => $this->userId];
		$this->initialState->provideInitialState('photos', $this->config->getAppValue('photos', 'enabled', 'no') === 'yes');
		$response = new TemplateResponse('maps', 'main', $params);

		$this->addCsp($response);

		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param $url
	 * @return TemplateResponse
	 */
	public function openGeoLink($url): TemplateResponse {
		return $this->index();
	}

	/**
	 * @param $response
	 * @return void
	 */
	private function addCsp($response): void {
		if (class_exists('OCP\AppFramework\Http\ContentSecurityPolicy')) {
			$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
			// map tiles
			$csp->addAllowedImageDomain('https://*.tile.openstreetmap.org');
			$csp->addAllowedImageDomain('https://tile.openstreetmap.org');
			$csp->addAllowedImageDomain('https://server.arcgisonline.com');
			$csp->addAllowedImageDomain('https://*.cartocdn.com');
			$csp->addAllowedImageDomain('https://*.opentopomap.org');
			$csp->addAllowedImageDomain('https://*.cartocdn.com');
			$csp->addAllowedImageDomain('https://*.ssl.fastly.net');
			$csp->addAllowedImageDomain('https://*.openstreetmap.se');

			// default routing engine
			$csp->addAllowedConnectDomain('https://*.project-osrm.org');
			$csp->addAllowedConnectDomain('https://api.mapbox.com');
			$csp->addAllowedConnectDomain('https://events.mapbox.com');
			$csp->addAllowedConnectDomain('https://graphhopper.com');

			$csp->addAllowedChildSrcDomain('blob:');
			$csp->addAllowedWorkerSrcDomain('blob:');
			$csp->addAllowedScriptDomain('https://unpkg.com');
			// allow connections to custom routing engines
			$urlKeys = [
				'osrmBikeURL',
				'osrmCarURL',
				'osrmFootURL',
				'graphhopperURL',
				'maplibreStreetStyleURL',
				'maplibreStreetStyleAuth'
			];
			foreach ($urlKeys as $key) {
				$url = $this->config->getAppValue('maps', $key);
				if ($url !== '') {
					$scheme = parse_url($url, PHP_URL_SCHEME);
					$host = parse_url($url, PHP_URL_HOST);
					$port = parse_url($url, PHP_URL_PORT);
					$cleanUrl = $scheme . '://' . $host;
					if ($port && $port !== '') {
						$cleanUrl .= ':' . $port;
					}
					$csp->addAllowedConnectDomain($cleanUrl);

					if ($host === 'api.protomaps.com') {
						$csp->addAllowedConnectDomain('https://protomaps.github.io');
					}
				}
			}
			//$csp->addAllowedConnectDomain('http://192.168.0.66:5000');

			// poi images
			$csp->addAllowedImageDomain('https://nominatim.openstreetmap.org');
			// search and geocoder
			$csp->addAllowedConnectDomain('https://nominatim.openstreetmap.org');
			$response->setContentSecurityPolicy($csp);
		}
	}
}
