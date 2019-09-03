<?php

declare(strict_types=1);

namespace OCA\Maps\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\IDBConnection;
use Doctrine\DBAL\Types\Type;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000102Date20190901152326 extends SimpleMigrationStep {

	protected $db;

	public function __construct(IDBConnection $connection) {
		$this->db = $connection;
	}

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
		$schema = $schemaClosure();

		if ($schema->hasTable('maps_address_geo')) {
			$table = $schema->getTable('maps_address_geo');
			$table->changeColumn('object_uri', [
				'notnull' => true,
				'default' => '',
				'length' => 255,
			]);
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
