<?php

namespace OCA\Maps\Migration;

use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UnregisterMimeType implements IRepairStep {
	public const CUSTOM_MIMETYPEMAPPING = 'mimetypemapping.json';

	protected IMimeTypeLoader $mimeTypeLoader;

	public function __construct(IMimeTypeLoader $mimeTypeLoader) {
		$this->mimeTypeLoader = $mimeTypeLoader;
	}

	public function getName() {
		return 'Unregister Maps MIME types';
	}

	private function unregisterForExistingFiles() {
		$mimeTypeId = $this->mimeTypeLoader->getId('application/octet-stream');
		$this->mimeTypeLoader->updateFilecache('maps', $mimeTypeId);
		$this->mimeTypeLoader->updateFilecache('noindex', $mimeTypeId);
		$this->mimeTypeLoader->updateFilecache('nomedia', $mimeTypeId);
		$this->mimeTypeLoader->updateFilecache('noimage', $mimeTypeId);
		$this->mimeTypeLoader->updateFilecache('notrack', $mimeTypeId);
	}

	private function unregisterForNewFiles() {
		$mappingFile = \OC::$configDir . self::CUSTOM_MIMETYPEMAPPING;

		if (file_exists($mappingFile)) {
			$mapping = json_decode(file_get_contents($mappingFile), true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($mapping)) {
				unset($mapping['maps']);
				unset($mapping['noindex']);
				unset($mapping['nomedia']);
				unset($mapping['noimage']);
				unset($mapping['notrack']);
			} else {
				$mapping = [];
			}
			file_put_contents($mappingFile, json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		}
	}

	public function run(IOutput $output) {
		$output->info('Unregistering the mimetype...');

		// Register the mime type for existing files
		$this->unregisterForExistingFiles();

		// Register the mime type for new files
		$this->unregisterForNewFiles();

		$output->info('The mimetype was successfully unregistered.');
	}
}
