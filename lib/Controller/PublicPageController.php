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

use OCA\Files\Event\LoadSidebar;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\AuthPublicShareController;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;

class PublicPageController extends AuthPublicShareController {
	use AddCspTrait;

	protected IShare $share;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		protected IEventDispatcher $eventDispatcher,
		IAppConfig $appConfig,
		protected IInitialState $initialState,
		protected ShareManager $shareManager,
		protected IUserManager $userManager,
	) {
		parent::__construct($appName, $request, $session, $urlGenerator);
		$this->appConfig = $appConfig;
	}

	public function isValidToken(): bool {
		try {
			$this->share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound $e) {
			return false;
		}

		return true;
	}

	protected function verifyPassword(string $password): bool {
		return $this->shareManager->checkPassword($this->share, $password);
	}

	protected function getPasswordHash(): ?string {
		return $this->share->getPassword();
	}

	protected function isPasswordProtected(): bool {
		return $this->share->getPassword() !== null;
	}

	/**
	 * Validate the permissions of the share
	 */
	private function validateShare(\OCP\Share\IShare $share): bool {
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
	 * @throws NotFoundException
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	private function getShareNode(): File|Folder {
		\OC_User::setIncognitoMode(true);

		// Check whether share exists
		try {
			$share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound $e) {
			// The share does not exist, we do not emit an ShareLinkAccessedEvent
			throw new NotFoundException();
		}

		if (!$this->validateShare($share)) {
			throw new NotFoundException();
		}

		return $share->getNode();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function showShare(): PublicTemplateResponse {
		$shareNode = $this->getShareNode();

		$this->eventDispatcher->dispatchTyped(new LoadSidebar());
		$this->eventDispatcher->dispatchTyped(new LoadViewer());

		$params = [];
		$params['sharingToken'] = $this->getToken();
		$this->initialState->provideInitialState('photos', $this->appConfig->getValueBool('photos', 'enabled'));
		$response = new PublicTemplateResponse('maps', 'public/main', $params);

		$this->addCsp($response);

		return $response;
	}

	/**
	 * Show the authentication page
	 * The form has to submit to the authenticate method route
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function showAuthenticate(): PublicTemplateResponse {
		$templateParameters = ['share' => $this->share];

		$this->eventDispatcher->dispatchTyped(new BeforeTemplateRenderedEvent($this->share, BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH));

		$response = new PublicTemplateResponse('core', 'publicshareauth', $templateParameters);
		if ($this->share->getSendPasswordByTalk()) {
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('blob:');
			$response->setContentSecurityPolicy($csp);
		}

		return $response;
	}

	/**
	 * The template to show when authentication failed
	 */
	protected function showAuthFailed(): PublicTemplateResponse {
		$templateParameters = ['share' => $this->share, 'wrongpw' => true];

		$this->eventDispatcher->dispatchTyped(new BeforeTemplateRenderedEvent($this->share, BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH));

		$response = new PublicTemplateResponse('core', 'publicshareauth', $templateParameters);
		if ($this->share->getSendPasswordByTalk()) {
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('blob:');
			$response->setContentSecurityPolicy($csp);
		}

		return $response;
	}

	/**
	 * The template to show after user identification
	 */
	protected function showIdentificationResult(bool $success = false): PublicTemplateResponse {
		$templateParameters = ['share' => $this->share, 'identityOk' => $success];

		$this->eventDispatcher->dispatchTyped(new BeforeTemplateRenderedEvent($this->share, BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH));

		$response = new PublicTemplateResponse('core', 'publicshareauth', $templateParameters);
		if ($this->share->getSendPasswordByTalk()) {
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('blob:');
			$response->setContentSecurityPolicy($csp);
		}

		return $response;
	}
}
