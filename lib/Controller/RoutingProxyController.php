<?php declare(strict_types=1);
/**
 * Nextcloud - Maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author aszlig <aszlig@nix.build>
 * @copyright aszlig 2019
 */

namespace OCA\Maps\Controller;

use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

use OCA\Maps\Http\ProxyResponse;

class RoutingProxyController extends Controller {
    private $logger;
    private $config;

    public function __construct(string $appname, IRequest $request,
                                ILogger $logger, IConfig $config) {
        parent::__construct($appname, $request);
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Build a query string from the current request combined with $extraQuery
     * and return it in a way that can be directly appended to an URL (eg. with
     * a leading '?').
     */
    private function buildQueryStringArg(array $extraQuery = []): string {
        // Unfortunately, we can't use $this->request->getParams() here,
        // because some services like GraphHopper use the same query string
        // arguments twice, like eg.: point=12.34,56.78&point=43.21,87.65
        $queryComponents = explode('&', $_SERVER['QUERY_STRING'] ?? '');

        if (count($queryComponents) == 0) {
            return '';
        }

        $query = [];
        foreach ($queryComponents as $comp) {
            $keyval = explode('=', $comp, 2);
            $key = rawurldecode($keyval[0]);
            $val = rawurldecode($keyval[1] ?? '');
            $query[$key][] = $val;
        }

        // XXX: PHP's array() "function" is *not* a ZEND_FUNCTION, so we can't
        //      simply do array_map('array', ...).
        $toSingleton = function ($a) { return [$a]; };

        $query = array_merge($query, array_map($toSingleton, $extraQuery));

        $result = [];
        foreach ($query as $key => $values) {
            foreach ($values as $value) {
                $keyEnc = rawurlencode($key);
                if ($value === null) {
                    $result[] = $keyEnc;
                } else {
                    $result[] = $keyEnc . '=' . rawurlencode($value);
                }
            }
        }
        return '?' . implode('&', $result);
    }

    /**
     * Send a request to the service at $baseUrl with path $path and the
     * current request query string params and return the response from the
     * remote server.
     */
    private function proxyResponse(string $baseUrl, string $path,
                                   array $extraQuery = []): Response {
        if ($baseUrl === '') {
            $response = new Response();
            $response->setStatus(Http::STATUS_NOT_ACCEPTABLE);
            return $response;
        }
        $url = $baseUrl . '/' . ltrim($path, '/');
        $url .= $this->buildQueryStringArg($extraQuery);
        $proxy = new ProxyResponse($url);
        $proxy->sendRequest($this->logger);
        return $proxy;
    }

    /**
     * Proxy routing request to either a configured OSRM instance or the demo
     * instance.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function requestOsrmRoute(string $profile, string $path): Response {
        if ($profile === 'demo') {
            $url = 'https://router.project-osrm.org/route/v1';
        } elseif ($profile === 'car') {
            $url = $this->config->getAppValue('maps', 'osrmCarURL');
        } elseif ($profile === 'bicycle') {
            $url = $this->config->getAppValue('maps', 'osrmBikeURL');
        } elseif ($profile === 'foot') {
            $url = $this->config->getAppValue('maps', 'osrmFootURL');
        } else {
            $this->logger->error(
                'Unknown profile '.$profile.' selected for OSRM.'
            );
            $response = new Response();
            $response->setStatus(Http::STATUS_BAD_REQUEST);
            return $response;
        }
        return $this->proxyResponse($url, $path);
    }

    /**
     * Proxy routing request to GraphHopper, injecting the API key.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function requestGraphHopperRoute(string $path): Response {
        $url = $this->config->getAppValue(
            'maps', 'graphhopperURL', 'https://graphhopper.com/api/1/route'
        );
        $apiKey = $this->config->getAppValue('maps', 'graphhopperAPIKEY');
        return $this->proxyResponse($url, $path, ['key' => $apiKey]);
    }

    /**
     * Proxy routing request to Mapbox, injecting the API key.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function requestMapboxRoute(string $path): Response {
        $url = 'https://api.mapbox.com/directions/v5';
        $apiKey = $this->config->getAppValue('maps', 'mapboxAPIKEY');
        return $this->proxyResponse($url, $path, ['access_token' => $apiKey]);
    }
}
