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
use OCP\IAvatarManager;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IDBConnection;
use OCP\AppFramework\Controller;
use OCP\Contacts\IManager;
use OCA\Maps\Service\AddressService;
use \OCP\DB\QueryBuilder\IQueryBuilder;
use \OCA\DAV\CardDAV\CardDavBackend;
use \Sabre\VObject\Property\Text;
use \Sabre\VObject\Reader;

class ContactsController extends Controller {
    private $userId;
    private $logger;
    private $contactsManager;
    private $addressService;
    private $dbconnection;
    private $qb;
    private $cdBackend;
    private $avatarManager;

    public function __construct($AppName, ILogger $logger, IRequest $request, IDBConnection $dbconnection,
                                IManager $contactsManager, AddressService $addressService,
                                $UserId, CardDavBackend $cdBackend, IAvatarManager $avatarManager){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->userId = $UserId;
        $this->avatarManager = $avatarManager;
        $this->contactsManager = $contactsManager;
        $this->addressService = $addressService;
        $this->dbconnection = $dbconnection;
        $this->qb = $dbconnection->getQueryBuilder();
        $this->cdBackend = $cdBackend;
    }

    /**
     * get contacts with coordinates
     * @NoAdminRequired
     */
    public function getContacts() {
        $contacts = $this->contactsManager->search('', ['GEO','ADR'], ['types'=>false]);
        $addressBooks = $this->contactsManager->getUserAddressBooks();
        $result = [];
        $userid = trim($this->userId);
        // get addressbook ids
        $bookids = [];
        foreach ($contacts as $c) {
            if (!in_array($c['addressbook-key'], $bookids)) {
                array_push($bookids, $c['addressbook-key']);
            }
        }
        // determine if addressbooks are enabled
        $bookIsEnabled = $this->getBookIsEnabled($bookids);

        foreach ($contacts as $c) {
            if (!$bookIsEnabled[$c['addressbook-key']]) {
                continue;
            }
            $addressBookUri = $addressBooks[$c['addressbook-key']]->getUri();
            $uid = trim($c['UID']);
            // we don't give users, just contacts
            if (strcmp($c['URI'], 'Database:'.$c['UID'].'.vcf') !== 0 and
                strcmp($uid, $userid) !== 0
            ) {
                // if the contact has a geo attibute use it
                if (key_exists('GEO', $c)) {
                    $geo = $c['GEO'];
                    if (strlen($geo) > 1) {
                        array_push($result, [
                            'FN' => $c['FN'] ?? $this->N2FN($c['N']) ?? '???',
                            'URI' => $c['URI'],
                            'UID' => $c['UID'],
                            'ADR' => '',
                            'ADRTYPE' => '',
                            'HAS_PHOTO' => (isset($c['PHOTO']) && $c['PHOTO'] !== null),
                            'BOOKID' => $c['addressbook-key'],
                            'BOOKURI' => $addressBookUri,
                            'GEO' => $geo,
                            'GROUPS' => $c['CATEGORIES'] ?? null
                        ]);
                    } elseif (count($geo)>0) {
						foreach ($geo as $g) {
							array_push($result, [
								'FN' => $c['FN'] ?? $this->N2FN($c['N']) ?? '???',
								'URI' => $c['URI'],
								'UID' => $c['UID'],
								'ADR' => '',
								'ADRTYPE' => '',
								'HAS_PHOTO' => (isset($c['PHOTO']) && $c['PHOTO'] !== null),
								'BOOKID' => $c['addressbook-key'],
								'BOOKURI' => $addressBookUri,
								'GEO' => $g,
								'GROUPS' => $c['CATEGORIES'] ?? null
							]);
						}
					}
                }
                // anyway try to get it from the address
                $card = $this->cdBackend->getContact($c['addressbook-key'], $c['URI']);
                if ($card) {
                    $vcard = Reader::read($card['carddata']);
                    if (isset($vcard->ADR) && count($vcard->ADR) > 0) {
                        foreach ($vcard->ADR as $adr) {
                            $geo = $this->addressService->addressToGeo($adr->getValue(), $c['URI']);
                            //var_dump($adr->parameters()['TYPE']->getValue());
                            $adrtype = '';
                            if (isset($adr->parameters()['TYPE'])) {
                                $adrtype = $adr->parameters()['TYPE']->getValue();
                            }
                            if (strlen($geo) > 1) {
                                array_push($result, [
                                    'FN' => $c['FN'] ?? $this->N2FN($c['N']) ?? '???',
                                    'URI' => $c['URI'],
                                    'UID' => $c['UID'],
                                    'ADR' => $adr->getValue(),
                                    'ADRTYPE' => $adrtype,
                                    'HAS_PHOTO' => (isset($c['PHOTO']) && $c['PHOTO'] !== null),
                                    'BOOKID' => $c['addressbook-key'],
                                    'BOOKURI' => $addressBookUri,
                                    'GEO' => $geo,
                                    'GROUPS' => $c['CATEGORIES'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }
        }
        return new DataResponse($result);
    }

    private function getBookIsEnabled($bookids) {
        $bookIsEnabled = [];
        $qb = $this->qb;
        foreach ($bookids as $bookid) {
            // first get the address book uri in oc_addressbooks
            $qb->select('uri', 'principaluri')
                ->from('addressbooks')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($bookid, IQueryBuilder::PARAM_INT)));
            $req = $qb->execute();
            $uri = null;
            $principaluri = null;
            while ($row = $req->fetch()) {
                $uri = $row['uri'];
                $principaluri = $row['principaluri'];
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();
            // then check its {http://owncloud.org/ns}enabled property in oc_properties
            if ($uri !== null) {
                $propertypath = str_replace('principals/users/', 'addressbooks/users/', $principaluri) . '/' . $uri;
                $qb->select('propertyvalue')
                    ->from('properties')
                    ->where($qb->expr()->eq('propertypath', $qb->createNamedParameter($propertypath, IQueryBuilder::PARAM_STR)))
                    ->andWhere($qb->expr()->eq('propertyname', $qb->createNamedParameter('{http://owncloud.org/ns}enabled', IQueryBuilder::PARAM_STR)));
                $req = $qb->execute();
                $propertyvalue = null;
                while ($row = $req->fetch()) {
                    $propertyvalue = intval($row['propertyvalue']);
                }
                $req->closeCursor();
                $qb = $qb->resetQueryParts();

                $bookIsEnabled[$bookid] = ($propertyvalue === null or $propertyvalue === 1);
            }
        }
        return $bookIsEnabled;
    }

    private function N2FN(string $n) {
        if ($n) {
            $spl = explode($n, ';');
            if (count($spl) >= 4) {
                return $spl[3] + ' ' + $spl[1] + ' ' + $spl[0];
            }
            else {
                return null;
            }
        }
        else {
            return null;
        }
    }

    /**
     * get all contacts
     * @NoAdminRequired
     */
    public function searchContacts(string $query = ''): DataResponse {
        $contacts = $this->contactsManager->search($query, ['FN'], ['types'=>false]);
        $booksReadOnly = $this->getAddressBooksReadOnly();
        $addressBooks = $this->contactsManager->getUserAddressBooks();
        $result = [];
        $userid = trim($this->userId);

        // get addressbook ids
        $bookids = [];
        foreach ($contacts as $c) {
            if (!in_array($c['addressbook-key'], $bookids)) {
                array_push($bookids, $c['addressbook-key']);
            }
        }
        // determine if addressbooks are enabled
        $bookIsEnabled = $this->getBookIsEnabled($bookids);

        foreach ($contacts as $c) {
            if (!$bookIsEnabled[$c['addressbook-key']]) {
                continue;
            }
            $uid = trim($c['UID']);
            // we don't give users, just contacts
            if (strcmp($c['URI'], 'Database:'.$c['UID'].'.vcf') !== 0 and
                strcmp($uid, $userid) !== 0
            ) {
                $addressBookUri = $addressBooks[$c['addressbook-key']]->getUri();
                array_push($result, [
                    'FN' => $c['FN'] ?? $this->N2FN($c['N']) ?? '???',
                    'URI' => $c['URI'],
                    'UID' => $c['UID'],
                    'BOOKID' => $c['addressbook-key'],
                    'READONLY' => $booksReadOnly[$c['addressbook-key']],
                    'BOOKURI' => $addressBookUri,
                    'HAS_PHOTO' => (isset($c['PHOTO']) && $c['PHOTO'] !== null),
                    'HAS_PHOTO2' => (isset($c['PHOTO']) && $c['PHOTO'] !== null && $c['PHOTO'] !== ''),
                ]);
            }
        }
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function placeContact($bookid, $uri, $uid, $lat, $lng, $attraction, $house_number, $road, $postcode, $city, $state, $country, $type) {
        // do not edit 'user' contact even myself
        if (strcmp($uri, 'Database:'.$uid.'.vcf') === 0 or
            strcmp($uid, $this->userId) === 0
        ) {
            return new DataResponse('Can\'t edit users', 400);
        }
        else {
            // check addressbook permissions
            if (!$this->addressBookIsReadOnly($bookid)) {
                if ($lat !== null && $lng !== null) {
                    // we set the geo tag
                    if (!$attraction && !$house_number && !$road && !$postcode && !$city && !$state && !$country) {
                        $result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'GEO'=>$lat.';'.$lng], $bookid);
                    }
                    // we set the address
                    else {
                        $street = trim($attraction.' '.$house_number.' '.$road);
                        $stringAddress = ';;'.$street.';'.$city.';'.$state.';'.$postcode.';'.$country;
                        // set the coordinates in the DB
                        $lat = floatval($lat);
                        $lng = floatval($lng);
                        $this->setAddressCoordinates($lat, $lng, $stringAddress, $uri);
                        // set the address in the vcard
                        $card = $this->cdBackend->getContact($bookid, $uri);
                        if ($card) {
                            $vcard = Reader::read($card['carddata']);;
                            $vcard->add(new Text($vcard, 'ADR', ['', '', $street, $city, $state, $postcode, $country], ['TYPE'=>$type]));
                            $this->cdBackend->updateCard($bookid, $uri, $vcard->serialize());
                        }
                    }
                }
                else {
                    // TODO find out how to remove a property
                    // following does not work properly
                    $result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'GEO'=>null], $bookid);
                }
                return new DataResponse('EDITED');
            }
            else {
                return new DataResponse('READONLY', 400);
            }
        }
    }

    private function addressBookIsReadOnly($bookid) {
        $userBooks = $this->cdBackend->getAddressBooksForUser('principals/users/'.$this->userId);
        foreach ($userBooks as $book) {
            if ($book['id'] === $bookid) {
                return (isset($book['{http://owncloud.org/ns}read-only']) and $book['{http://owncloud.org/ns}read-only']);
            }
        }
        return true;
    }

    private function getAddressBooksReadOnly() {
        $booksReadOnly = [];
        $userBooks = $this->cdBackend->getAddressBooksForUser('principals/users/'.$this->userId);
        foreach ($userBooks as $book) {
            $ro = (isset($book['{http://owncloud.org/ns}read-only']) and $book['{http://owncloud.org/ns}read-only']);
            $booksReadOnly[$book['id']] = $ro;
        }
        return $booksReadOnly;
    }

    private function setAddressCoordinates($lat, $lng, $adr, $uri) {
        $qb = $this->qb;
        $adr_norm = strtolower(preg_replace('/\s+/', '', $adr));

        $qb->select('id')
             ->from('maps_address_geo')
             ->where($qb->expr()->eq('adr_norm', $qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR)))
             ->andWhere($qb->expr()->eq('object_uri', $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR)));
        $req = $qb->execute();
        $result = $req->fetchAll();
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        if ($result and count($result) > 0) {
            $id = $result[0]['id'];
            $qb->update('maps_address_geo')
                ->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR))
                ->set('lng', $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR))
                ->set('object_uri', $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR))
                ->set('looked_up', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();
        }
        else {
            $qb->insert('maps_address_geo')
                ->values([
                    'adr'=>$qb->createNamedParameter($adr, IQueryBuilder::PARAM_STR),
                    'adr_norm'=>$qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR),
                    'object_uri'=>$qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR),
                    'lat'=>$qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
                    'lng'=>$qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR),
                    'looked_up'=>$qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
                ]);
            $req = $qb->execute();
            $id = $qb->getLastInsertId();
            $qb = $qb->resetQueryParts();
        }
    }

    /**
     * get contacts with coordinates
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getContactLetterAvatar($name) {
        $av = $this->avatarManager->getGuestAvatar($name);
        $avatarContent = $av->getFile(64)->getContent();
        return new DataDisplayResponse($avatarContent);
    }

    /**
     * removes the address from the vcard
     * and delete corresponding entry in the DB
     * @NoAdminRequired
     */
    public function deleteContactAddress($bookid, $uri, $uid, $adr, $geo) {
        // vcard
        $card = $this->cdBackend->getContact($bookid, $uri);
        if ($card) {
            $vcard = Reader::read($card['carddata']);
            //$bookId = $card['addressbookid'];
            if (!$this->addressBookIsReadOnly($bookid)) {
                foreach ($vcard->children() as $property) {
                    if ($property->name === 'ADR') {
                        $cardAdr = $property->getValue();
                        if ($cardAdr === $adr) {
                            $vcard->remove($property);
                            break;
                        }
                    } elseif ($property->name === 'GEO') {
						$cardAdr = $property->getValue();
						if ($cardAdr === $geo) {
							$vcard->remove($property);
							break;
						}
					}
                }
                $this->cdBackend->updateCard($bookid, $uri, $vcard->serialize());
                // no need to cleanup db here, it will be done when catching vcard change hook
                return new DataResponse('DELETED');
            }
            else {
                return new DataResponse('READONLY', 400);
            }
        }
        else {
            return new DataResponse('FAILED', 400);
        }
    }
}
