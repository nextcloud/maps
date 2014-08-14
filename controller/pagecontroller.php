<?php
/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>
 * @copyright Sander Brand 2014
 */

namespace OCA\Maps\Controller;


use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Controller;

class PageController extends Controller {

    private $userId;

    public function __construct($appName, IRequest $request, $userId){
        parent::__construct($appName, $request);
        $this->userId = $userId;
    }


    /**
     * CAUTION: the @Stuff turn off security checks, for this page no admin is
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
        return new TemplateResponse('maps', 'main', $params);  // templates/main.php
    }


    /**
     * Simply method that posts back the payload of the request
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function doProxy($echo) {
        $url =  ($this -> params('url')) ? $this -> params('url') : '';
		$allowedHosts = array('dev.virtualearth.net','router.project-osrm.org','nominatim.openstreetmap.org');
		$parseUrl = parse_url($url);
		//print_r($parseUrl);
		
		if(in_array($parseUrl['host'],$allowedHosts)){
			header('Content-Type: application/javascript');
			$split = explode('url=',$_SERVER['REQUEST_URI']);
			echo $this->getURL($split[1]);
		}
		die();
    }
	
	private function getURL($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 GTB5');
		curl_setopt($ch, CURLOPT_URL, $url);
		$tmp = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($httpCode == 404) {
			return false;
		} else {
			if ($tmp != false) {
				return $tmp;
			}
		}

	}


}