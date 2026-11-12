<?php

namespace rdx\db\PhpStan;

use db_generic;
use db_generic_keyless_model;
use db_generic_relationship;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\Annotations\AnnotationPropertyReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\MixedType;
use ReflectionMethod;
use Throwable;

final class AroPropertyExtension implements PropertiesClassReflectionExtension {

	private const DEBUG_CLASS = '-';
	private const DEBUG_PROPERTY = '-';

	/** @var array<class-string<db_generic_keyless_model>, db_generic_keyless_model> */
	protected array $objects = [];

	/** @var array<class-string<db_generic_keyless_model>, list<string>> */
	protected array $dbTables = [];

	protected db_generic $fakeDb;

	public function __construct(
		protected ?TypeStringResolver $typeStringResolver,
		protected DbSchema $schema,
	) {
		db_generic_keyless_model::$_db = $this->fakeDb = $this->createFakeDb();
	}

	public function hasProperty(ClassReflection $classReflection, string $propertyName) : bool {
		$className = $classReflection->getName();

		$debugClass = $className === self::DEBUG_CLASS;
		$debugProperty = $propertyName === self::DEBUG_PROPERTY;

		if (!$classReflection->isSubclassOf(db_generic_keyless_model::class)) {
			if ($debugProperty) dump(__LINE__);
			return false;
		}

		if ($classReflection->isAbstract()) {
			if ($debugProperty) dump(__LINE__);
			return false;
		}

		$phpdoc = $classReflection->getResolvedPhpDoc();
		if ($phpdoc) {
			$propertyTags = $phpdoc->getPropertyTags();
			if (isset($propertyTags[$propertyName])) {
				if ($debugProperty) dump(__LINE__);
				return false;
			}
		}

		// try {
			$object = $this->getObject($className);
			if ($debugClass) dump($propertyName, $object);
		// }
		// catch (Throwable $ex) {
		// 	if ($debugProperty) dump($className, $propertyName, $ex);
		// 	return false;
		// }

		if (null !== $this->getObjectValue($object, $propertyName)) {
			if ($debugProperty) dump(__LINE__);
			return true;
		}

		if ($this->existsRelationship($object, $propertyName) || $this->existsGetter($object, $propertyName)) {
			if ($debugProperty) dump(__LINE__);
			return true;
		}

		if ($debugProperty) dump(__LINE__);
		return false;
	}

	public function getProperty(ClassReflection $classReflection, string $propertyName) : PropertyReflection {
		$className = $classReflection->getName();

		$debugProperty = $propertyName === self::DEBUG_PROPERTY;

		$object = $this->getObject($className);

		$type = new MixedType();
		$writable = true;

		// ARO database/init() field
		if (null !== ($value = $this->getObjectValue($object, $propertyName))) {
			if ($debugProperty) dump(__LINE__);
			$type = $this->typeStringResolver->resolve(gettype($value));
		}
		// Database field
		elseif ($field = $this->getField($classReflection, $propertyName)) {
			if ($debugProperty) dump(__LINE__);
			$type = $this->typeStringResolver->resolve($field->getNullablePhpType());
		}
		// ARO relate_*
		elseif ($this->existsRelationship($object, $propertyName)) {
			if ($debugProperty) dump(__LINE__);
			$relationMethod = 'relate_' . $propertyName;
			$reflMethod = new ReflectionMethod($className, $relationMethod); // @phpstan-ignore phpstanApi.runtimeReflection
			/** @var db_generic_relationship<db_generic_keyless_model> $relationship */
			$relationship = $reflMethod->invoke($object);
			$type = $this->typeStringResolver->resolve($relationship->getReturnType());
			// $type = new MixedType();
		}
		// ARO get_*
		elseif ($this->existsGetter($object, $propertyName)) {
			if ($debugProperty) dump(__LINE__);
			$methodRefl = $classReflection->getMethod('get_' . $propertyName, new OutOfClassScope());
			$type = $methodRefl->getVariants()[0]->getReturnType();
		}

		return new AnnotationPropertyReflection( // @phpstan-ignore phpstanApi.constructor
			$classReflection->getName(),
			$classReflection,
			readableType: $type,
			writableType: $type,
			readable: true,
			writable: $writable,
		);
	}

	protected function existsGetter(db_generic_keyless_model $object, string $propertyName) : bool {
		return method_exists($object, 'get_' . $propertyName);
	}

	protected function existsRelationship(db_generic_keyless_model $object, string $propertyName) : bool {
		return method_exists($object, 'relate_' . $propertyName);
	}

	/**
	 *
	 */
	protected function getField(ClassReflection $classReflection, string $propertyName) : ?SchemaFieldDefinition {
		$className = $classReflection->getName();
		$dbTables = $this->getDbTables($className);
// dump($classReflection->getName(), $propertyName, $dbTables);

		foreach ($dbTables as $tableName) {
			$field = $this->schema->getField($tableName, $propertyName);
			if ($field) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	protected function getDbTables(string $className) : array {
		if (isset($this->dbTables[$className])) {
			return $this->dbTables[$className];
		}

		return $this->dbTables[$className] = [$className::$_table];
	}

	protected function getObject(string $className) : db_generic_keyless_model {
		if (isset($this->objects[$className])) {
			return $this->objects[$className];
		}

		$dbTables = $this->getDbTables($className);
		$values = [];
		foreach ($dbTables as $tableName) {
			$fields = $this->schema->getFields($tableName);
			$values += array_map(fn($field) => $field->makeValue(), $fields);
		}

		return $this->objects[$className] = new $className($values);
	}

	protected function getObjectValue(db_generic_keyless_model $object, string $propertyName) : mixed {
		$vars = get_object_vars($object);
		return $vars[$propertyName] ?? null;
	}

	protected function createFakeDb() : db_generic {
		return new FakeDb;
	}

}
