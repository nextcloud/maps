<?php

namespace OCA\Maps\Migration;

use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RegisterMimeType implements IRepairStep {
	public const CUSTOM_MIMETYPEMAPPING = 'mimetypemapping.json';

	protected IMimeTypeLoader $mimeTypeLoader;

	public function __construct(IMimeTypeLoader $mimeTypeLoader) {
		$this->mimeTypeLoader = $mimeTypeLoader;
	}

	public function getName() {
		return 'Register Maps MIME types';
	}

	private function registerForExistingFiles() {
		$mimeTypeId = $this->mimeTypeLoader->getId('application/x-nextcloud-maps');
		$this->mimeTypeLoader->updateFilecache('maps', $mimeTypeId);

		$mimeTypeId = $this->mimeTypeLoader->getId('application/x-nextcloud-noindex');
		$this->mimeTypeLoader->updateFilecache('noindex', $mimeTypeId);

		$mimeTypeId = $this->mimeTypeLoader->getId('application/x-nextcloud-nomedia');
		$this->mimeTypeLoader->updateFilecache('nomedia', $mimeTypeId);

		$mimeTypeId = $this->mimeTypeLoader->getId('application/x-nextcloud-noimage');
		$this->mimeTypeLoader->updateFilecache('noimage', $mimeTypeId);

		$mimeTypeId = $this->mimeTypeLoader->getId('application/x-nextcloud-maps-notrack');
		$this->mimeTypeLoader->updateFilecache('notrack', $mimeTypeId);
	}

	private function registerForNewFiles() {
		$mapping = [
			'maps' => ['application/x-nextcloud-maps'],
			'noindex' => ['application/x-nextcloud-noindex'],
			'nomedia' => ['application/x-nextcloud-nomedia'],
			'noimage' => ['application/x-nextcloud-noimage'],
			'notrack' => ['application/x-nextcloud-maps-notrack'],
		];
		$mappingFile = \OC::$configDir . self::CUSTOM_MIMETYPEMAPPING;

		if (file_exists($mappingFile)) {
			$existingMapping = json_decode(file_get_contents($mappingFile), true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($existingMapping)) {
				$mapping = array_merge($existingMapping, $mapping);
			}
		}

		file_put_contents($mappingFile, json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	}

	public function run(IOutput $output) {
		$output->info('Registering the mimetype...');

		// Register the mime type for existing files
		$this->registerForExistingFiles();

		// Register the mime type for new files
		$this->registerForNewFiles();

		$output->info('The mimetype was successfully registered.');
	}
}
