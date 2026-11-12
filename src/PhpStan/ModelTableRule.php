<?php

namespace rdx\db\PhpStan;

use db_generic_keyless_model;
use db_generic_model;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
final class ModelTableRule implements Rule {

	public function __construct(
		protected DbSchema $schema,
	) {}

	public function getNodeType() : string {
		return InClassNode::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$classReflection = $node->getClassReflection();
		if (!$classReflection->isSubclassOf(db_generic_keyless_model::class)) {
			return [];
		}

		if ($classReflection->isAbstract()) {
			return [];
		}

		$className = $classReflection->getName();
		$tableName = $className::$_table;

		if (!$this->schema->hasTable($tableName)) {
			$message = sprintf("Model %s uses table '%s', which is not in the schema file.", $className, $tableName);
			$error = RuleErrorBuilder::message($message)
				->identifier('rdxDb.modelTableUnknown')
				->build();
			return [$error];
		}

		$hasId = $this->schema->getField($tableName, 'id') !== null;
		$isKeyed = $classReflection->isSubclassOf(db_generic_model::class);

		if ($isKeyed && !$hasId) {
			$message = sprintf("Model %s extends db_generic_model, but table '%s' has no id column. Extend db_generic_keyless_model instead.", $className, $tableName);
			$error = RuleErrorBuilder::message($message)
				->identifier('rdxDb.modelTableNoId')
				->build();
			return [$error];
		}

		if (!$isKeyed && $hasId) {
			$message = sprintf("Model %s extends db_generic_keyless_model, but table '%s' has an id column. Extend db_generic_model instead.", $className, $tableName);
			$error = RuleErrorBuilder::message($message)
				->identifier('rdxDb.keylessTableHasId')
				->build();
			return [$error];
		}

		return [];
	}

}
