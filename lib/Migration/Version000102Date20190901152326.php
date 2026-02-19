<?php

declare(strict_types=1);

namespace OCA\Maps\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000102Date20190901152326 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $db,
	) {
	}

	/**
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		if ($schema->hasTable('maps_address_geo')) {
			$table = $schema->getTable('maps_address_geo');
			$table->changeColumn('object_uri', [
				'notnull' => true,
				'default' => '--',
				'length' => 255,
			]);
		}

		return $schema;
	}

	/**
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
