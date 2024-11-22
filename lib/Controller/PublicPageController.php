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

use OC\Security\CSP\ContentSecurityPolicy;
use OCA\Files\Event\LoadSidebar;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\AuthPublicShareController;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;

class PublicPageController extends AuthPublicShareController {
	protected IShare $share;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		protected IEventDispatcher $eventDispatcher,
		protected IConfig $config,
		protected IInitialStateService $initialStateService,
		protected ShareManager $shareManager,
		protected IUserManager $userManager,
	) {
		parent::__construct($appName, $request, $session, $urlGenerator);
		$this->eventDispatcher = $eventDispatcher;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->shareManager = $shareManager;
		$this->userManager = $userManager;
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
	 *
	 * @return bool
	 */
	private function validateShare(\OCP\Share\IShare $share) {
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
	 * @return \OCP\Files\File|\OCP\Files\Folder
	 * @throws NotFoundException
	 */
	private function getShareNode() {
		\OC_User::setIncognitoMode(true);

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

		return $share->getNode();
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function showShare(): PublicTemplateResponse {
		$shareNode = $this->getShareNode();

		$this->eventDispatcher->dispatch(LoadSidebar::class, new LoadSidebar());
		$this->eventDispatcher->dispatch(LoadViewer::class, new LoadViewer());

		$params = [];
		$params['sharingToken'] = $this->getToken();
		$this->initialStateService->provideInitialState($this->appName, 'photos', $this->config->getAppValue('photos', 'enabled', 'no') === 'yes');
		$response = new PublicTemplateResponse('maps', 'public/main', $params);

		$this->addCsp($response);

		return $response;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * Show the authentication page
	 * The form has to submit to the authenticate method route
	 */
	public function showAuthenticate(): PublicTemplateResponse {
		$templateParameters = ['share' => $this->share];

		$this->eventDispatcher->dispatchTyped(new BeforeTemplateRenderedEvent($this->share, BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH));

		$response = new PublicTemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
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

		$response = new PublicTemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
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

		$response = new PublicTemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
		if ($this->share->getSendPasswordByTalk()) {
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('blob:');
			$response->setContentSecurityPolicy($csp);
		}

		return $response;
	}


	/**
	 * @param $response
	 * @return void
	 */
	private function addCsp($response): void {
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

			$csp->addAllowedChildSrcDomain('blob:');
			$csp->addAllowedWorkerSrcDomain('blob:');
			$csp->addAllowedScriptDomain('https://unpkg.com');
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

			// poi images
			$csp->addAllowedImageDomain('https://nominatim.openstreetmap.org');
			// search and geocoder
			$csp->addAllowedConnectDomain('https://nominatim.openstreetmap.org');
			$response->setContentSecurityPolicy($csp);
		}
	}
}
