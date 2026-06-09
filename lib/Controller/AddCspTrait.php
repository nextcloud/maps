<?php

namespace OCA\Maps\Controller;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\Response;
use OCP\IAppConfig;

trait AddCspTrait {
	private IAppConfig $appConfig;

	private function addCsp(Response $response): void {
		$csp = new ContentSecurityPolicy();
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

		$csp->addAllowedWorkerSrcDomain('blob:');
		$csp->addAllowedScriptDomain('https://unpkg.com');
		// allow connections to custom routing engines
		$urlKeys = [
			'osrmBikeURL',
			'osrmCarURL',
			'osrmFootURL',
			'graphhopperURL'
		];
		foreach ($urlKeys as $key) {
			$url = $this->appConfig->getValueString('maps', $key);
			if ($url !== '') {
				$scheme = parse_url($url, PHP_URL_SCHEME);
				$host = parse_url($url, PHP_URL_HOST);
				$port = parse_url($url, PHP_URL_PORT);
				$cleanUrl = $scheme . '://' . $host;
				if ($port && $port !== '') {
					$cleanUrl .= ':' . $port;
				}
				$csp->addAllowedConnectDomain($cleanUrl);
			}
		}

		// poi images
		$csp->addAllowedImageDomain('https://nominatim.openstreetmap.org');
		// search and geocoder
		$csp->addAllowedConnectDomain('https://nominatim.openstreetmap.org');
		$response->setContentSecurityPolicy($csp);
	}
}
