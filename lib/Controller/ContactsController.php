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

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\Maps\Service\AddressService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\Contacts\IManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IAvatarManager;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IURLGenerator;
use Sabre\VObject\Property\Text;
use Sabre\VObject\Reader;

class ContactsController extends Controller {
	private $userId;
	private $contactsManager;
	private $addressService;
	private $dbconnection;
	private $cdBackend;
	private $avatarManager;
	private $root;
	private $urlGenerator;
	private $geoDistanceMax; // Max distance in meters to consider that 2 addresses are the same location

	/**
	 * @param $AppName
	 * @param IRequest $request
	 * @param IDBConnection $dbconnection
	 * @param IManager $contactsManager
	 * @param AddressService $addressService
	 * @param $UserId
	 * @param CardDavBackend $cdBackend
	 * @param IAvatarManager $avatarManager
	 * @param IRootFolder $root
	 */
	public function __construct(
		$AppName,
		IRequest $request,
		IDBConnection $dbconnection,
		IManager $contactsManager,
		AddressService $addressService,
		$UserId,
		CardDavBackend $cdBackend,
		IAvatarManager $avatarManager,
		IRootFolder $root,
		IURLGenerator $urlGenerator) {
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->avatarManager = $avatarManager;
		$this->contactsManager = $contactsManager;
		$this->addressService = $addressService;
		$this->dbconnection = $dbconnection;
		$this->cdBackend = $cdBackend;
		$this->root = $root;
		$this->urlGenerator = $urlGenerator;
		$this->geoDistanceMax = 5;
	}
	/**
	 * Converts a geo string as a float array
	 * @param string formatted as "lat;lon"
	 * @return float[] array containing [lat;lon]
	 */
	private function geoAsFloatArray($geo) {
		$res = array_map(function ($value) {return floatval($value);}, explode(';', $geo));
		return $res;
	}

	/**
	 * check if geographical address is duplicated
	 * @param array containing contact's previous different addresses
	 * @param contact's address to check
	 * @return integer : -1 if address is new, index of duplicated address in other cases
	 */
	private function isNewAddress($prevGeo, $geo) {
		if (empty($geo)) { // Address not converted to geo coords
			return -1;
		}
		$result = -1;
		$counter = 0;
		foreach ($prevGeo as $prev) {
			if ($this->getDistance($prev, $geo) <= $this->geoDistanceMax) {
				$result = $counter;
				break;
			}
			$counter++;
		}
		return $result;
	}

	/**
	 * get distance between two geo points
	 * @param GPS coordinates of first point
	 * @param GPS coordinates of second point
	 * @return float Distance in meters between these two points
	 */
	private function getDistance($coordsA, $coordsB) {
		if (empty($coordsA) || empty($coordsB)) {
			return 9E999;
		}
		$latA = deg2rad($coordsA[0]);
		$lonA = deg2rad($coordsA[1]);
		$latB = deg2rad($coordsB[0]);
		$lonB = deg2rad($coordsB[1]);
		$earthRadius = 6378137; // in m
		$dlon = ($lonB - $lonA) / 2;
		$dlat = ($latB - $latA) / 2;
		$a = (sin($dlat) * sin($dlat)) + cos($latA) * cos($latB) * (sin($dlon) * sin($dlon
		));
		$d = 2 * atan2(sqrt($a), sqrt(1 - $a));
		return $d * $earthRadius;
	}

	/**
	 * get contacts with coordinates
	 *
	 * @NoAdminRequired
	 * @param null $myMapId
	 * @return DataResponse
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function getContacts($myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === '') {
			$contacts = $this->contactsManager->search('', ['GEO', 'ADR'], ['types' => false]);
			$addressBooks = $this->contactsManager->getUserAddressBooks();
			$result = [];
			$userid = trim($this->userId);

			foreach ($contacts as $c) {
				$addressBookUri = $addressBooks[$c['addressbook-key']]->getUri();
				$uid = trim($c['UID']);

				$url = $this->directUrlToContact($uid, $addressBookUri);

				// we don't give users, just contacts
				if (strcmp($c['URI'], 'Database:' . $c['UID'] . '.vcf') !== 0 and
					strcmp($uid, $userid) !== 0
				) {
					// if the contact has a geo attibute use it
					if (key_exists('GEO', $c)) {
						$geo = $c['GEO'];
						if (is_string($geo) && strlen($geo) > 1) {
							$result[] = [
								'FN' => $c['FN'] ?? $this->N2FN($c['N']) ?? '???',
								'URI' => $c['URI'],
								'UID' => $c['UID'],
								'URL' => $url,
								'ADR' => '',
								'ADRTYPE' => '',
								'HAS_PHOTO' => (isset($c['PHOTO']) && $c['PHOTO'] !== null),
								'BOOKID' => $c['addressbook-key'],
								'BOOKURI' => $addressBookUri,
								'GEO' => $geo,
								'GROUPS' => $c['CATEGORIES'] ?? null,
								'isDeletable' => true,
								'isUpdateable' => true,
							];
						} elseif (is_countable($geo) && count($geo) > 0 && is_iterable($geo)) {
							foreach ($geo as $g) {
								if (is_string($g) && strlen($g) > 1) {
									$result[] = [
										'FN' => $c['FN'] ?? $this->N2FN($c['N']) ?? '???',
										'URI' => $c['URI'],
										'UID' => $c['UID'],
										'URL' => $url,
										'ADR' => '',
										'ADRTYPE' => '',
										'HAS_PHOTO' => (isset($c['PHOTO']) && $c['PHOTO'] !== null),
										'BOOKID' => $c['addressbook-key'],
										'BOOKURI' => $addressBookUri,
										'GEO' => $g,
										'GROUPS' => $c['CATEGORIES'] ?? null,
										'isDeletable' => true,
										'isUpdateable' => true,
									];
								}
							}
						}
					}
					// anyway try to get it from the address
					$card = $this->cdBackend->getContact($c['addressbook-key'], $c['URI']);
					if ($card) {
						$vcard = Reader::read($card['carddata']);
						if (isset($vcard->ADR) && count($vcard->ADR) > 0) {
							$prevGeo = [];
							$prevRes = [];
							foreach ($vcard->ADR as $adr) {
								$geo = $this->addressService->addressToGeo($adr->getValue(), $c['URI']);
								$geof = $this->geoAsFloatArray($geo);
								$duplicatedIndex = $this->isNewAddress($prevGeo, $geof);
								$adrtype = '';
								if (isset($adr->parameters()['TYPE'])) {
									$adrtype = $adr->parameters()['TYPE']->getValue();
								}
								if (is_string($geo) && strlen($geo) > 1) {
									if ($duplicatedIndex < 0) {
										array_push($prevGeo, $geof);
										array_push($prevRes, count($result)); // Add index of new item so that we can update the ADRTYPE in case of duplicate address
										$result[] = [
											'FN' => $c['FN'] ?? $this->N2FN($c['N']) ?? '???',
											'URI' => $c['URI'],
											'UID' => $c['UID'],
											'URL' => $url,
											'ADR' => $adr->getValue(),
											'ADRTYPE' => [$adrtype],
											'HAS_PHOTO' => (isset($c['PHOTO']) && $c['PHOTO'] !== null),
											'BOOKID' => $c['addressbook-key'],
											'BOOKURI' => $addressBookUri,
											'GEO' => $geo,
											'GROUPS' => $c['CATEGORIES'] ?? null,
											'isDeletable' => true,
											'isUpdateable' => true,
										];
									} else {
										// Concatenate AddressType to the corresponding record
										array_push($result[$prevRes[$duplicatedIndex]]['ADRTYPE'], $adrtype);
										$result[$prevRes[$duplicatedIndex]]['isUpdateable'] = false;
										$result[$prevRes[$duplicatedIndex]]['isDeletable'] = false;
										$result[$prevRes[$duplicatedIndex]]['isShareable'] = false;
									}
								}
							}
						}
					}
				}
			}
			return new DataResponse($result);
		} else {
			//Fixme add contacts for my-maps
			$result = [];
			$userFolder = $this->root->getUserFolder($this->userId);
			$folders = $userFolder->getById($myMapId);
			if (empty($folders)) {
				return new DataResponse($result);
			}
			$folder = array_shift($folders);
			if ($folder === null) {
				return new DataResponse($result);
			}
			$files = $folder->search('.vcf');
			foreach ($files as $file) {
				//				$cards = explode("END:VCARD\r\n", $file->getContent());
				$cards = [$file->getContent()];
				foreach ($cards as $card) {
					$vcard = Reader::read($card."END:VCARD\r\n");
					if (isset($vcard->GEO)) {
						$geo = $vcard->GEO;
						if (is_string($geo) && strlen($geo->getValue()) > 1) {
							$result[] = $this->vCardToArray($file, $vcard, $geo->getValue());
						} elseif (is_countable($geo) && count($geo) > 0 && is_iterable($geo)) {
							$prevGeo = '';
							foreach ($geo as $g) {
								if (strcmp($prevGeo, $g->getValue()) != 0) {
									$prevGeo = $g->getValue();
									if (strlen($g->getValue()) > 1) {
										$result[] = $this->vCardToArray($file, $vcard, $g->getValue());
									}
								}
							}
						}
					}
					if (isset($vcard->ADR) && count($vcard->ADR) > 0) {
						$prevGeo = [];
						$prevRes = [];
						foreach ($vcard->ADR as $adr) {
							$geo = $this->addressService->addressToGeo($adr->getValue(), $file->getId());
							$geof = $this->geoAsFloatArray($geo);
							$duplicatedIndex = $this->isNewAddress($prevGeo, $geof);
							//var_dump($adr->parameters()['TYPE']->getValue());
							$adrtype = '';
							if (isset($adr->parameters()['TYPE'])) {
								$adrtype = $adr->parameters()['TYPE']->getValue();
							}
							if (is_string($geo) && strlen($geo) > 1) {
								if ($duplicatedIndex < 0) {
									array_push($prevGeo, $geof);
									array_push($prevRes, count($result)); // Add index of new item so that we can update the ADRTYPE in case of duplicate address
									$result[] = $this->vCardToArray($file, $vcard, $geo, $adrtype, $adr->getValue(), $file->getId());
								} else {
									// Concatenate AddressType to the corresponding record
									array_push($result[$prevRes[$duplicatedIndex]]['ADRTYPE'], $adrtype);
									$result[$prevRes[$duplicatedIndex]]['isUpdateable'] = false;
									$result[$prevRes[$duplicatedIndex]]['isDeletable'] = false;
									$result[$prevRes[$duplicatedIndex]]['isShareable'] = false;
								}
							}
						}
					}
				}
			}
			return new DataResponse($result);
		}
	}

	/**
	 * @param $contactUid
	 * @param $addressBookUri
	 * @return string
	 */
	private function directUrlToContact($contactUid, $addressBookUri) {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('contacts.contacts.direct', [
				'contact' => $contactUid . '~' . $addressBookUri
			])
		);
	}

	/**
	 * @param Node $file
	 * @param \Sabre\VObject\Document $vcard
	 * @param string $geo
	 * @param string|null $adrtype
	 * @param string|null $adr
	 * @param int|null $fileId
	 * @return array
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 */
	private function vCardToArray(Node $file, \Sabre\VObject\Document $vcard, string $geo, ?string $adrtype = null, ?string $adr = null, ?int $fileId = null): array {
		$FNArray = $vcard->FN ? $vcard->FN->getJsonValue() : [];
		$fn = array_shift($FNArray);
		$NArray = $vcard->N ? $vcard->N->getJsonValue() : [];
		$n = array_shift($NArray);
		if (!is_null($n)) {
			if (is_array($n)) {
				$n = $this->N2FN(array_shift($n));
			} elseif (is_string($n)) {
				$n = $this->N2FN($n);
			}

		}
		$UIDArray = $vcard->UID->getJsonValue();
		$uid = array_shift($UIDArray);
		$groups = $vcard->CATEGORIES;
		if (!is_null($groups)) {
			$groups = $groups->getValue();
		} else {
			$groups = '';
		}
		$result = [
			'FN' => $fn ?? $n ?? '???',
			'UID' => $uid,
			'HAS_PHOTO' => (isset($vcard->PHOTO) && $vcard->PHOTO !== null),
			'FILEID' => $fileId,
			'ADR' => $adr ?? '',
			'ADRTYPE' => $adrtype ?? '',
			'PHOTO' => $vcard->PHOTO ?? '',
			'GEO' => $geo,
			'GROUPS' => $groups,
			'isDeletable' => $file->isDeletable(),
			'isUpdateable' => $file->isUpdateable(),
		];
		return $result;
	}

	/**
	 * @param string $n
	 * @return string|null
	 */
	private function N2FN(string $n): ?string {
		if ($n) {
			$spl = explode($n, ';');
			if (count($spl) >= 4) {
				return $spl[3] . ' ' . $spl[1] . ' ' . $spl[0];
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

	/**
	 * get all contacts
	 *
	 * @NoAdminRequired
	 * @param string $query
	 * @return DataResponse
	 */
	public function searchContacts(string $query = ''): DataResponse {
		$contacts = $this->contactsManager->search($query, ['FN'], ['types' => false]);
		$booksReadOnly = $this->getAddressBooksReadOnly();
		$addressBooks = $this->contactsManager->getUserAddressBooks();
		$result = [];
		$userid = trim($this->userId);
		foreach ($contacts as $c) {
			$uid = trim($c['UID']);
			// we don't give users, just contacts
			if (strcmp($c['URI'], 'Database:'.$c['UID'].'.vcf') !== 0 and
				strcmp($uid, $userid) !== 0
			) {
				$addressBookUri = $addressBooks[$c['addressbook-key']]->getUri();
				$result[] = [
					'FN' => $c['FN'] ?? $this->N2FN($c['N']) ?? '???',
					'URI' => $c['URI'],
					'UID' => $c['UID'],
					'BOOKID' => $c['addressbook-key'],
					'READONLY' => $booksReadOnly[$c['addressbook-key']],
					'BOOKURI' => $addressBookUri,
					'HAS_PHOTO' => (isset($c['PHOTO'])),
					'HAS_PHOTO2' => (isset($c['PHOTO']) && $c['PHOTO'] !== ''),
				];
			}
		}
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * @param string $bookid
	 * @param string $uri
	 * @param string $uid
	 * @param float|null $lat
	 * @param float|null $lng
	 * @param string $attraction
	 * @param string $house_number
	 * @param string $road
	 * @param string $postcode
	 * @param string $city
	 * @param string $state
	 * @param string $country
	 * @param string $type
	 * @param string|null $address_string
	 * @param int|null $fileId
	 * @param int|null $myMapId
	 * @return DataResponse
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function placeContact(
		string $bookid,
		string $uri,
		string $uid,
		?float $lat,
		?float $lng,
		string $attraction = '',
		string $house_number = '',
		string $road = '',
		string $postcode = '',
		string $city = '',
		string $state = '',
		string $country = '',
		string $type = '',
		?string $address_string = null,
		?int $fileId = null,
		?int $myMapId = null): DataResponse {
		if (is_null($myMapId) || $myMapId === '') {
			// do not edit 'user' contact even myself
			if (strcmp($uri, 'Database:'.$uid.'.vcf') === 0 or
				strcmp($uid, $this->userId) === 0
			) {
				return new DataResponse('Can\'t edit users', 400);
			} else {
				// check addressbook permissions
				if (!$this->addressBookIsReadOnly($bookid)) {
					if ($lat !== null && $lng !== null) {
						// we set the geo tag
						if (!$attraction && !$house_number && !$road && !$postcode && !$city && !$state && !$country && !$address_string) {
							$result = $this->contactsManager->createOrUpdate(['URI' => $uri, 'GEO' => $lat.';'.$lng], $bookid);
						}
						// we set the address
						elseif (!$address_string) {
							$street = trim($attraction.' '.$house_number.' '.$road);
							$stringAddress = ';;'.$street.';'.$city.';'.$state.';'.$postcode.';'.$country;
							// set the coordinates in the DB
							$lat = floatval($lat);
							$lng = floatval($lng);
							$this->setAddressCoordinates($lat, $lng, $stringAddress, $uri);
							// set the address in the vcard
							$card = $this->cdBackend->getContact($bookid, $uri);
							if ($card) {
								$vcard = Reader::read($card['carddata']);
								;
								$vcard->add(new Text($vcard, 'ADR', ['', '', $street, $city, $state, $postcode, $country], ['TYPE' => $type]));
								$result = $this->cdBackend->updateCard($bookid, $uri, $vcard->serialize());
							}
						} else {
							$card = $this->cdBackend->getContact($bookid, $uri);
							if ($card) {
								$vcard = Reader::read($card['carddata']);
								;
								$vcard->add(new Text($vcard, 'ADR', explode(';', $address_string), ['TYPE' => $type]));
								$result = $this->cdBackend->updateCard($bookid, $uri, $vcard->serialize());
							}
						}
					} else {
						// TODO find out how to remove a property
						// following does not work properly
						$result = $this->contactsManager->createOrUpdate(['URI' => $uri, 'GEO' => null], $bookid);
					}
					return new DataResponse('EDITED');
				} else {
					return new DataResponse('READONLY', 400);
				}
			}
		} else {
			$userFolder = $this->root->getUserFolder($this->userId);
			$folders = $userFolder->getById($myMapId);
			if (empty($folders)) {
				return new DataResponse('MAP NOT FOUND', 404);
			}
			$mapsFolder = array_shift($folders);
			if (is_null($mapsFolder)) {
				return new DataResponse('MAP NOT FOUND', 404);
			}
			if (is_null($fileId)) {
				$card = $this->cdBackend->getContact($bookid, $uri);
				try {
					$file = $mapsFolder->get($uri);
				} catch (NotFoundException $e) {
					if (!$mapsFolder->isCreatable()) {
						return new DataResponse('CONTACT NOT WRITABLE', 400);
					}
					$file = $mapsFolder->newFile($uri);
				}
			} else {
				$files = $mapsFolder->getById($fileId);
				if (empty($files)) {
					return new DataResponse('CONTACT NOT FOUND', 404);
				}
				$file = array_shift($files);
				if (is_null($file)) {
					return new DataResponse('CONTACT NOT FOUND', 404);
				}
				$card = $file->getContent();
			}
			if (!$file->isUpdateable()) {
				return new DataResponse('CONTACT NOT WRITABLE', 400);
			}
			if ($card) {
				$vcard = Reader::read($card['carddata']);
				if ($lat !== null && $lng !== null) {
					if (!$attraction && !$house_number && !$road && !$postcode && !$city && !$state && !$country && !$address_string) {
						$vcard->add('GEO', $lat.';'.$lng);
					} elseif (!$address_string) {
						$street = trim($attraction.' '.$house_number.' '.$road);
						$stringAddress = ';;'.$street.';'.$city.';'.$state.';'.$postcode.';'.$country;
						// set the coordinates in the DB
						$lat = floatval($lat);
						$lng = floatval($lng);
						$this->setAddressCoordinates($lat, $lng, $stringAddress, $uri);
						$vcard = Reader::read($card['carddata']);
						$vcard->add('ADR', ['', '', $street, $city, $state, $postcode, $country], ['TYPE' => $type]);
					} else {
						$stringAddress = $address_string;
						// set the coordinates in the DB
						$lat = floatval($lat);
						$lng = floatval($lng);
						$this->setAddressCoordinates($lat, $lng, $stringAddress, $uri);
						$vcard = Reader::read($card['carddata']);
						$vcard->add('ADR', explode(';', $address_string), ['TYPE' => $type]);
					}
				} else {
					$vcard->remove('GEO');
				}
				$file->putContent($vcard->serialize());
				return new DataResponse('EDITED');
			}
			return new DataResponse('CONTACT NOT FOUND', 404);
		}

	}

	/**
	 * @NoAdminRequired
	 * @param string $bookid
	 * @param string $uri
	 * @param int $myMapId
	 * @param int|null $fileId
	 * @return DataResponse|void
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function addContactToMap(string $bookid, string $uri, int $myMapId, ?int $fileId = null): DataResponse {
		$userFolder = $this->root->getUserFolder($this->userId);
		$folders = $userFolder->getById($myMapId);
		if (empty($folders)) {
			return new DataResponse('MAP NOT FOUND', 404);
		}
		$mapsFolder = array_shift($folders);
		if (is_null($mapsFolder)) {
			return new DataResponse('MAP NOT FOUND', 404);
		}
		if (is_null($fileId)) {
			$card = $this->cdBackend->getContact($bookid, $uri);
			try {
				$file = $mapsFolder->get($uri);
			} catch (NotFoundException $e) {
				if (!$mapsFolder->isCreatable()) {
					return new DataResponse('CONTACT NOT WRITABLE', 400);
				}
				$file = $mapsFolder->newFile($uri);
			}
		} else {
			$files = $mapsFolder->getById($fileId);
			if (empty($files)) {
				return new DataResponse('CONTACT NOT FOUND', 404);
			}
			$file = array_shift($files);
			if (is_null($file)) {
				return new DataResponse('CONTACT NOT FOUND', 404);
			}
			$card = $file->getContent();
		}
		if (!$file->isUpdateable()) {
			return new DataResponse('CONTACT NOT WRITABLE', 400);
		}
		if ($card) {
			$vcard = Reader::read($card['carddata']);
			$file->putContent($vcard->serialize());
			return new DataResponse('DONE');
		}
	}

	/**
	 * @param string $bookid
	 * @return bool
	 */
	private function addressBookIsReadOnly(string $bookid): bool {
		$userBooks = $this->cdBackend->getAddressBooksForUser('principals/users/'.$this->userId);
		foreach ($userBooks as $book) {
			if ($book['id'] === (int)$bookid) {
				return (isset($book['{http://owncloud.org/ns}read-only']) and $book['{http://owncloud.org/ns}read-only']);
			}
		}
		return true;
	}

	/**
	 * @return array
	 */
	private function getAddressBooksReadOnly(): array {
		$booksReadOnly = [];
		$userBooks = $this->cdBackend->getAddressBooksForUser('principals/users/'.$this->userId);
		foreach ($userBooks as $book) {
			$ro = (isset($book['{http://owncloud.org/ns}read-only']) and $book['{http://owncloud.org/ns}read-only']);
			$booksReadOnly[$book['id']] = $ro;
		}
		return $booksReadOnly;
	}

	/**
	 * @param float $lat
	 * @param float $lng
	 * @param string $adr
	 * @param string $uri
	 * @return void
	 * @throws \OCP\DB\Exception
	 */
	private function setAddressCoordinates(float $lat, float $lng, string $adr, string $uri): void {
		$qb = $this->dbconnection->getQueryBuilder();
		$adr_norm = strtolower(preg_replace('/\s+/', '', $adr));

		$qb->select('id')
			->from('maps_address_geo')
			->where($qb->expr()->eq('adr_norm', $qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('object_uri', $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR)));
		$req = $qb->execute();
		$result = $req->fetchAll();
		$req->closeCursor();
		$qb = $this->dbconnection->getQueryBuilder();
		if ($result and count($result) > 0) {
			$id = $result[0]['id'];
			$qb->update('maps_address_geo')
				->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR))
				->set('lng', $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR))
				->set('object_uri', $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR))
				->set('looked_up', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
			$req = $qb->execute();

		} else {
			$qb->insert('maps_address_geo')
				->values([
					'adr' => $qb->createNamedParameter($adr, IQueryBuilder::PARAM_STR),
					'adr_norm' => $qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR),
					'object_uri' => $qb->createNamedParameter($uri, IQueryBuilder::PARAM_STR),
					'lat' => $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
					'lng' => $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR),
					'looked_up' => $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
				]);
			$req = $qb->execute();
			$id = $qb->getLastInsertId();
		}
	}


	/**
	 * get contacts with coordinates
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param string $name
	 * @return DataDisplayResponse
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function getContactLetterAvatar(string $name): DataDisplayResponse {
		$av = $this->avatarManager->getGuestAvatar($name);
		$avatarContent = $av->getFile(64)->getContent();
		return new DataDisplayResponse($avatarContent);
	}

	/**
	 * removes the address from the vcard
	 * and delete corresponding entry in the DB
	 *
	 * @NoAdminRequired
	 * @param string $bookid
	 * @param string $uri
	 * @param string $uid
	 * @param string $adr
	 * @param string $geo
	 * @param ?int $fileId
	 * @param ?int $myMapId
	 * @return DataResponse
	 */
	public function deleteContactAddress($bookid, $uri, $uid, $adr, $geo, $fileId = null, $myMapId = null): DataResponse {

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
			} else {
				return new DataResponse('READONLY', 400);
			}
		} else {
			return new DataResponse('FAILED', 400);
		}
	}
}
