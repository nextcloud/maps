<?php

namespace OCA\Maps\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {

	public function __construct(
		private IL10N $l,
		private IConfig $config,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$keys = [
			'osrmCarURL',
			'osrmBikeURL',
			'osrmFootURL',
			'osrmDEMO',
			'graphhopperAPIKEY',
			'mapboxAPIKEY',
			'maplibreStreetStyleURL',
			'maplibreStreetStyleAuth',
			'graphhopperURL'
		];
		$parameters = [];
		foreach ($keys as $k) {
			$v = $this->config->getAppValue('maps', $k);
			$parameters[$k] = $v;
		}

		return new TemplateResponse('maps', 'adminSettings', $parameters, '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'additional';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 5;
	}

}
