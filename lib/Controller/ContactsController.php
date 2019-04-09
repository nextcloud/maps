<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Piotr Bator <prbator@gmail.com>
 * @copyright Piotr Bator 2017
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
     * TODO avoid strange contacts with URI like Database:toto.vcf
     */
    public function getAllContacts() {
        $contacts = $this->contactsManager->search('', ['FN'], ['types'=>false]);
        $result = [];
        foreach ($contacts as $c) {
            array_push($result, [
                'FN'=>$c['FN'],
                'URI'=>$c['URI'],
                'UID'=>$c['UID'],
                'BOOKID'=>$c['addressbook-key']
            ]);
        }
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function placeContact($bookid, $uri, $lat, $lng) {
        $result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'GEO'=>$lat.';'.$lng], $bookid);
        return new DataResponse('EDITED');
    }

}
