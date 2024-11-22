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
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\Contacts\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IInitialStateService;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Sabre\VObject\Reader;

class PublicContactsController extends PublicPageController {
	protected IManager $contactsManager;
	protected AddressService $addressService;
	protected CardDavBackend $cdBackend;
	protected IAvatarManager $avatarManager;
	protected IRootFolder $root;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		IEventDispatcher $eventDispatcher,
		IConfig $config,
		IInitialStateService $initialStateService,
		ShareManager $shareManager,
		IUserManager $userManager,
		IManager $contactsManager,
		IDBConnection $dbconnection,
		AddressService $addressService,
		CardDavBackend $cdBackend,
		IAvatarManager $avatarManager,
		IRootFolder $root) {
		parent::__construct($appName, $request, $session, $urlGenerator, $eventDispatcher, $config, $initialStateService, $shareManager, $userManager);
		$this->avatarManager = $avatarManager;
		$this->contactsManager = $contactsManager;
		$this->addressService = $addressService;
		$this->cdBackend = $cdBackend;
		$this->root = $root;
	}

	/**
	 * Validate the permissions of the share
	 */
	private function validateShare(IShare $share): bool {
		// If the owner is disabled no access to the link is granted
		$owner = $this->userManager->get($share->getShareOwner());
		if ($owner === null || !$owner->isEnabled()) {
			return false;
		}

		// If the initiator of the share is disabled no access is granted
		$initiator = $this->userManager->get($share->getSharedBy());
		if ($initiator === null || !$initiator->isEnabled()) {
			return false;
		}

		return $share->getNode()->isReadable() && $share->getNode()->isShareable();
	}

	/**
	 * @return IShare
	 * @throws NotFoundException
	 */
	private function getShare() {
		// Check whether share exists
		try {
			$share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound $e) {
			// The share does not exists, we do not emit an ShareLinkAccessedEvent
			throw new NotFoundException();
		}

		if (!$this->validateShare($share)) {
			throw new NotFoundException();
		}
		return $share;
	}

	/**
	 * @return \OCP\Files\File|\OCP\Files\Folder
	 * @throws NotFoundException
	 */
	private function getShareNode() {
		\OC_User::setIncognitoMode(true);

		$share = $this->getShare();

		return $share->getNode();
	}

	/**
	 * @PublicPage
	 *
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function getContacts(): DataResponse {
		$share = $this->getShare();
		$permissions = $share->getPermissions();
		$folder = $this->getShareNode();
		$isReadable = (bool)($permissions & (1 << 0));
		if ($isReadable) {
			//Fixme add contacts for my-maps
			$result = [];
			$files = $folder->search('.vcf');
			foreach ($files as $file) {
				//				$cards = explode("END:VCARD\r\n", $file->getContent());
				$cards = [$file->getContent()];
				foreach ($cards as $card) {
					$vcard = Reader::read($card."END:VCARD\r\n");
					if (isset($vcard->GEO)) {
						$geo = $vcard->GEO;
						if (is_string($geo->getValue()) && strlen($geo->getValue()) > 1) {
							$result[] = $this->vCardToArray($permissions, $file, $vcard, $geo->getValue());
						} elseif (is_countable($geo) && count($geo) > 0 && is_iterable($geo)) {
							foreach ($geo as $g) {
								if (strlen($g->getValue()) > 1) {
									$result[] = $this->vCardToArray($permissions, $file, $vcard, $g->getValue());
								}
							}
						}
					}
					if (isset($vcard->ADR) && count($vcard->ADR) > 0) {
						foreach ($vcard->ADR as $adr) {
							$geo = $this->addressService->addressToGeo($adr->getValue(), $file->getId());
							//var_dump($adr->parameters()['TYPE']->getValue());
							$adrtype = '';
							if (isset($adr->parameters()['TYPE'])) {
								$adrtype = $adr->parameters()['TYPE']->getValue();
							}
							if (is_string($geo) && strlen($geo) > 1) {
								$result[] = $this->vCardToArray($permissions, $file, $vcard, $geo, $adrtype, $adr->getValue(), $file->getId());
							}
						}
					}
				}
			}
			return new DataResponse($result);
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @param int $sharePermissions
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
	private function vCardToArray(int $sharePermissions, Node $file, \Sabre\VObject\Document $vcard, string $geo, ?string $adrtype = null, ?string $adr = null, ?int $fileId = null): array {
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
			'isReadable' => $file->isReadable() && ($sharePermissions & (1 << 0)),
			'isDeletable' => $file->isDeletable() && ($sharePermissions & (1 << 1)),
			'isUpdateable' => $file->isUpdateable() && ($sharePermissions & (1 << 3)),
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
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $name
	 * @return DataDisplayResponse
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getContactLetterAvatar(string $name): DataDisplayResponse {
		$av = $this->avatarManager->getGuestAvatar($name);
		$avatarContent = $av->getFile(64)->getContent();
		return new DataDisplayResponse($avatarContent);
	}
}
