<?php

declare(strict_types=1);

namespace OCA\Maps\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000009Date20190625000800 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('maps_address_geo')) {
			$table = $schema->createTable('maps_address_geo');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('adr', 'string', [
				'notnull' => true,
				'length' => 255,
				'unique' => true,
			]);
			$table->addColumn('adr_norm', 'string', [
				'notnull' => true,
				'length' => 255,
				'unique' => true,
			]);
			$table->addColumn('lat', 'float', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('lng', 'float', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('looked_up', 'boolean', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['adr'], 'maps_adr');
			$table->addIndex(['adr_norm'], 'maps_adr_norm');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
