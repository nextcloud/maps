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
class Version000013Date20190723185417 extends SimpleMigrationStep {

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
			if ($table->hasColumn('contact_uid')) {
				$table->dropColumn('contact_uid');
			}

			$table->addColumn('object_uri', 'string', [
				'notnull' => true,
				'default' => '--',
				'length' => 64,
			]);
			$table->addIndex(['object_uri'], 'maps_object_uri');
		}

		return $schema;
	}

	/**
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->db->getQueryBuilder();
		$query->delete('maps_address_geo');
		$query->executeStatement();
	}
}
