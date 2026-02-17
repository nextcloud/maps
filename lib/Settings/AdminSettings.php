<?php

declare(strict_types=1);

namespace OCA\Maps\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\Settings\ISettings;
use Override;

class AdminSettings implements ISettings {

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}

	#[Override]
	public function getForm(): TemplateResponse {
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
			$v = $this->appConfig->getValueString('maps', $k);
			$parameters[$k] = $v;
		}

		return new TemplateResponse('maps', 'adminSettings', $parameters, '');
	}

	#[Override]
	public function getSection(): string {
		return 'additional';
	}

	#[Override]
	public function getPriority(): int {
		return 5;
	}

}
