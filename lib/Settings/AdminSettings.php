<?php

namespace OCA\Maps\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {

	public function __construct(
		private readonly \OCP\IAppConfig $appConfig,
		private readonly IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		$keys = [
			'osrmCarURL',
			'osrmBikeURL',
			'osrmFootURL',
			'graphhopperAPIKEY',
			'mapboxAPIKEY',
			'maplibreStreetStyleURL',
			'maplibreStreetStyleAuth',
			'graphhopperURL',
		];
		$parameters = [];
		foreach ($keys as $k) {
			$parameters[$k] = $this->appConfig->getValueString('maps', $k);
		}
		// osrmDEMO defaults to enabled unless explicitly disabled
		$parameters['osrmDEMO'] = $this->appConfig->getValueString('maps', 'osrmDEMO') !== '0';
		$parameters['maplibreStreetStylePmtiles'] = $this->appConfig->getValueString('maps', 'maplibreStreetStylePmtiles') === '1';

		$this->initialState->provideInitialState('adminSettings', $parameters);

		return new TemplateResponse('maps', 'adminSettings', [], '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection(): string {
		return 'additional';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority(): int {
		return 5;
	}

}
