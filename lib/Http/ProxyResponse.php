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

namespace OCA\Maps\Http;

use OCP\ILogger;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

class ProxyResponse extends Response {
    const USER_AGENT = 'Nextcloud Maps (https://github.com/nextcloud/maps)';
    const REQUEST_TIMEOUT = 20;

    // NOTE: These need to be lower-case!
    const ALLOWED_HEADERS = ['content-type', 'content-length'];

    private $url;
    private $responseBody = '';

    public function __construct(string $url) {
        $this->url = $url;
    }

    /**
     * Send the API request to the given URL and set headers for our response
     * appropriately.
     */
    public function sendRequest(ILogger $logger): bool {
        if (($curl = curl_init()) === false) {
            $logger->error('Unable to initialise cURL');
            $this->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
            return false;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $this->url);
        curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);

        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($_, string $hl) {
            $keyval = explode(':', $hl, 2);
            if (count($keyval) === 2 && in_array(strtolower($keyval[0]),
                                                 self::ALLOWED_HEADERS)) {
                $this->addHeader(trim($keyval[0]), ltrim($keyval[1]));
            }
            return strlen($hl);
        });

        $response = curl_exec($curl);

        if ($response === false) {
            $logger->error('Error while proxying request to '.$this->url.': '.
                           curl_error($curl));
            curl_close($curl);
            $this->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
            return false;
        }

        $this->setStatus(curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        $this->responseBody = $response;
        curl_close($curl);
        return true;
    }

    public function render(): string {
        return $this->responseBody;
    }
}
