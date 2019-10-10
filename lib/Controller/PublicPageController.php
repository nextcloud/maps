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

use OC\User\Manager;
use OCA\Maps\Service\FavoritesService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\PublicShareController;

class PublicPageController extends PublicShareController
{
    private $config;
    private $logger;
    private $favoritesService;

    public function __construct($appName, IRequest $request, ISession $session, IConfig $config, ILogger $logger, FavoritesService $favoritesService)
    {
        parent::__construct($appName, $request, $session);
        $this->config = $config;
        $this->logger = $logger;
        $this->favoritesService = $favoritesService;
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

        $share = $this->favoritesService->getFavoritesShare($token);


        $response = new PublicTemplateResponse('maps', 'public/favorites_index', []);

        if ($share !== false) {
            $ownerName = \OC::$server->getUserManager()->get($share['owner'])->getDisplayName();

            $response->setHeaderTitle($share['category']);
            $response->setHeaderDetails('shared by ' . $ownerName);
        }

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
        return ""; // TODO:
    }

    /**
     * Is the provided token a valid token
     *
     * This function is already called from the middleware directly after setting the token.
     *
     * @since 14.0.0
     */
    public function isValidToken(): bool {
        return $this->favoritesService->getFavoritesShare($this->getToken()) !== null;
    }

    /**
     * Is a share with this token password protected
     *
     * @since 14.0.0
     */
    protected function isPasswordProtected(): bool {
        return false; // TODO
    }

    private function addCsp($response)
    {
        if (class_exists('OCP\AppFramework\Http\ContentSecurityPolicy')) {
            $csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
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
