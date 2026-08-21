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
use OCA\Maps\Service\MyMapsService;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IURLGenerator;

class PageController extends Controller {
	use AddCspTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private IEventDispatcher $eventDispatcher,
		IAppConfig $appConfig,
		private IInitialState $initialState,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);

		$this->appConfig = $appConfig;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$this->eventDispatcher->dispatchTyped(new LoadSidebar());
		$this->eventDispatcher->dispatchTyped(new LoadViewer());

		$params = ['user' => $this->userId];
		$this->initialState->provideInitialState('photos', $this->appConfig->getValueBool('photos', 'enabled'));
		$response = new TemplateResponse('maps', 'main', $params);

		$this->addCsp($response);

		return $response;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexMyMap(int $myMapId, MyMapsService $service): TemplateResponse|RedirectResponse {
		$userId = $this->userId;
		if ($userId === null) {
			throw new \LogicException('User must be logged in');
		}

		$map = $service->getMyMap($myMapId, $userId);
		if ($map !== null && $map['id'] !== $myMapId) {
			// Instead of the id of the map containing folder the '.index.maps' file id was passed so redirect
			// this happens if coming from the files app integration
			return new RedirectResponse(
				$this->urlGenerator->linkToRouteAbsolute('maps.page.indexMyMap', ['myMapId' => $map['id']]),
			);
		}

		$this->eventDispatcher->dispatchTyped(new LoadSidebar());
		$this->eventDispatcher->dispatchTyped(new LoadViewer());

		$params = ['user' => $userId];
		$this->initialState->provideInitialState('photos', $this->appConfig->getValueBool('photos', 'enabled'));
		$response = new TemplateResponse('maps', 'main', $params);

		$this->addCsp($response);

		return $response;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function openGeoLink(string $url): TemplateResponse {
		return $this->index();
	}
}
