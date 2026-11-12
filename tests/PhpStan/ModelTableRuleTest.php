<?php

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use rdx\db\PhpStan\DbSchema;
use rdx\db\PhpStan\ModelTableRule;

require_once __DIR__ . '/data/models.php';

/**
 * @extends RuleTestCase<ModelTableRule>
 */
class ModelTableRuleTest extends RuleTestCase {

	protected function getRule() : Rule {
		$schema = new DbSchema(__DIR__ . '/data/schema.php');
		return new ModelTableRule($schema);
	}

	public function testRule() : void {
		$this->analyse([__DIR__ . '/data/models.php'], [
			[
				"Model RuleKeyedWithoutId extends db_generic_model, but table 'pivot' has no id column. Extend db_generic_keyless_model instead.",
				7,
			],
			[
				"Model RuleKeylessWithId extends db_generic_keyless_model, but table 'keyed' has an id column. Extend db_generic_model instead.",
				15,
			],
			[
				"Model RuleUnknownTable uses table 'unknown', which is not in the schema file.",
				19,
			],
		]);
	}

}
