<?php
/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Maps\Controller;

use OCA\Maps\DB\FavoriteShareMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\PublicShareController;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserManager;
use OCP\Util;

class PublicFavoritePageController extends PublicShareController {
	private $config;

	/* @var FavoriteShareMapper */
	private $favoriteShareMapper;

	public function __construct(
		$appName,
		IRequest $request,
		ISession $session,
		IConfig $config,
		FavoriteShareMapper $favoriteShareMapper
	) {
		parent::__construct($appName, $request, $session);
		$this->config = $config;
		$this->favoriteShareMapper = $favoriteShareMapper;
	}

	/**
	 * @param $token
	 *
	 * @return DataResponse|PublicTemplateResponse
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function sharedFavoritesCategory($token) {
		if ($token === '') {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$share = $this->favoriteShareMapper->findByToken($token);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (MultipleObjectsReturnedException $e) {
			return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		Util::addStyle($this->appName, 'merged-public-favorite-share');
		Util::addScript($this->appName, 'maps-publicFavoriteShare');

		$response = new PublicTemplateResponse('maps', 'public/favorites_index', []);

		$ownerName = \OCP\Server::get(IUserManager::class)->get($share->getOwner())->getDisplayName();

		$response->setHeaderTitle($share->getCategory());
		$response->setHeaderDetails('shared by ' . $ownerName);

		$this->addCsp($response);

		return $response;
	}

	/**
	 * Get a hash of the password for this share
	 *
	 * To ensure access is blocked when the password to a share is changed we store
	 * a hash of the password for this token.
	 *
	 * @since 14.0.0
	 */
	protected function getPasswordHash(): string {
		return '';
	}

	/**
	 * Is the provided token a valid token
	 *
	 * This function is already called from the middleware directly after setting the token.
	 *
	 * @return bool
	 * @since 14.0.0
	 */
	public function isValidToken(): bool {
		try {
			$this->favoriteShareMapper->findByToken($this->getToken());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Is a share with this token password protected
	 *
	 * @return bool
	 * @since 14.0.0
	 */
	protected function isPasswordProtected(): bool {
		return false;
	}

	/**
	 * @param $response
	 * @return void
	 */
	private function addCsp($response): void {
		if (class_exists('OCP\AppFramework\Http\ContentSecurityPolicy')) {
			$csp = new ContentSecurityPolicy();
			// map tiles
			$csp->addAllowedImageDomain('https://*.tile.openstreetmap.org');
			$csp->addAllowedImageDomain('https://server.arcgisonline.com');
			$csp->addAllowedImageDomain('https://*.cartocdn.com');
			$csp->addAllowedImageDomain('https://*.opentopomap.org');
			$csp->addAllowedImageDomain('https://*.cartocdn.com');
			$csp->addAllowedImageDomain('https://*.ssl.fastly.net');
			$csp->addAllowedImageDomain('https://*.openstreetmap.se');

			// default routing engine
			$csp->addAllowedConnectDomain('https://*.project-osrm.org');
			$csp->addAllowedConnectDomain('https://api.mapbox.com');
			$csp->addAllowedConnectDomain('https://graphhopper.com');
			// allow connections to custom routing engines
			$urlKeys = [
				'osrmBikeURL',
				'osrmCarURL',
				'osrmFootURL',
				'graphhopperURL'
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
