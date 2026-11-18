<?php

namespace rdx\db\PhpStan;

use db_generic_keyless_model;
use db_generic_relationship;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use ReflectionMethod;

final class EagerReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension {

	public function __construct(
		protected ReflectionProvider $reflectionProvider,
		protected TypeStringResolver $typeStringResolver,
	) {}

	public function getClass() : string {
		return db_generic_keyless_model::class;
	}

	public function isStaticMethodSupported(MethodReflection $methodReflection) : bool {
		return $methodReflection->getName() === 'eager';
	}

	public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope) : ?Type {
		$relationship = $this->getRelationship($methodCall, $scope);
		if (!$relationship) {
			return null;
		}

		$returnType = $this->typeStringResolver->resolve($relationship->getReturnType());
		$targetType = TypeCombinator::removeNull($returnType);
		if ($targetType->isArray()->yes()) {
			$targetType = $targetType->getIterableValueType();
		}
		if (!$targetType->isObject()->yes()) {
			$targetType = TypeCombinator::addNull($targetType);
		}

		return new ArrayType(new IntegerType(), $targetType);
	}

	/**
	 * @return ?db_generic_relationship<db_generic_keyless_model>
	 */
	protected function getRelationship(StaticCall $methodCall, Scope $scope) : ?db_generic_relationship {
		if (!$methodCall->class instanceof Name) {
			return null;
		}

		$args = $methodCall->getArgs();
		if (!isset($args[0])) {
			return null;
		}

		$nameType = $scope->getType($args[0]->value);
		$names = $nameType->getConstantStrings();
		if (count($names) !== 1) {
			return null;
		}

		$className = $scope->resolveName($methodCall->class);
		if (!$this->reflectionProvider->hasClass($className)) {
			return null;
		}

		$classReflection = $this->reflectionProvider->getClass($className);
		$relationMethod = 'relate_' . $names[0]->getValue();
		if ($classReflection->isAbstract() || !$classReflection->hasNativeMethod($relationMethod)) {
			return null;
		}

		$object = new $className();
		$reflMethod = new ReflectionMethod($className, $relationMethod); // @phpstan-ignore phpstanApi.runtimeReflection
		$relationship = $reflMethod->invoke($object);
		if (!$relationship instanceof db_generic_relationship) {
			return null;
		}

		return $relationship;
	}

}
