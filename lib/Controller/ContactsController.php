<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\AppFramework\Controller;
use OCP\Contacts\IManager;

class ContactsController extends Controller {
    private $userId;
    private $logger;
    private $contactsManager;

    public function __construct($AppName, ILogger $logger, IRequest $request, IManager $contactsManager, $UserId){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->userId = $UserId;
        $this->contactsManager = $contactsManager;
    }

    /**
     * @NoAdminRequired
     */
    public function getContacts() {
        $result = $this->contactsManager->search('', ['GEO'], ['types'=>false]);
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function getAllContacts() {
        $contacts = $this->contactsManager->search('', ['FN'], ['types'=>false]);
        $result = [];
        $userid = trim($this->userId);
        foreach ($contacts as $c) {
            $uid = trim($c['UID']);
            if (strcmp($c['URI'], 'Database:'.$c['UID'].'.vcf') !== 0 or
                strcmp($uid, $userid) === 0
            ) {
                array_push($result, [
                    'FN'=>$c['FN'],
                    'URI'=>$c['URI'],
                    'UID'=>$c['UID'],
                    'BOOKID'=>$c['addressbook-key']
                ]);
            }
        }
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function placeContact($bookid, $uri, $uid, $lat, $lng) {
        // do not edit 'user' contact except myself
        if (strcmp($uri, 'Database:'.$uid.'.vcf') === 0 and
            strcmp($uid, $this.userId) !== 0
        ) {
            return new DataResponse('Can\'t edit users', 400);
        }
        else {
            // TODO check addressbook permissions
            // it's currently possible to place a contact from an addressbook shared with readonly permissions...
            $result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'GEO'=>$lat.';'.$lng], $bookid);
            return new DataResponse('EDITED');
        }
    }

}
