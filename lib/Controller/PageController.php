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

use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IInitialStateService;

class PageController extends Controller {
    private $userId;
    private $config;

    public function __construct($AppName,
                                IRequest $request,
                                $UserId,
                                IConfig $config,
                                IInitialStateService $initialStateService){
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->config = $config;
        $this->initialStateService = $initialStateService;
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
     */
    public function index() {
        $params = array('user' => $this->userId);
        $this->initialStateService->provideInitialState($this->appName, 'photos', $this->config->getAppValue('photos', 'enabled', 'no') === 'yes');
        $response = new TemplateResponse('maps', 'index', $params);
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
            $csp->addAllowedConnectDomain('https://events.mapbox.com');
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
        return $response;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function openGeoLink($url) {
        $params = array('user' => $this->userId);
        $params["geourl"]  = $url;
        $response = new TemplateResponse('maps', 'index', $params);
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
            // routing engine
            $csp->addAllowedConnectDomain('https://*.project-osrm.org');
            // TODO allow connections to router engine
            //$csp->addAllowedConnectDomain('http://192.168.0.66:8989');
            // poi images
            $csp->addAllowedImageDomain('https://nominatim.openstreetmap.org');
            // search and geocoder
            $csp->addAllowedConnectDomain('https://nominatim.openstreetmap.org');
            $response->setContentSecurityPolicy($csp);
        }
        return $response;
    }

}
