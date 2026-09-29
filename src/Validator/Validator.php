<?php

declare(strict_types=1);

namespace Hit\Validator;

use Hit\Db\Dto;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class Validator {
	/**
	 * Validate a DTO using #[Rule] attributes on its public properties.
	 *
	 * @return array<string, string> keyed by property name, value as PREFIX.property.rule
	 */
	public static function check(Dto $dto): array {
		$invalid = [];
		$prefix = $dto::PREFIX;

		foreach (new ReflectionClass($dto)->getProperties() as $property) {
			$attributes = $property->getAttributes(Rule::class);
			if ($attributes === []) {
				continue;
			}

			/** @var Rule $rule */
			$rule = $attributes[0]->newInstance();
			$rules = $rule->validations;
			$name = $property->getName();
			$value = $property->getValue($dto);

			$required = ($rules['required'] ?? false) === true;

			if ($required) {
				if (!Validation::required($value)) {
					$invalid[$name] = "{$prefix}.{$name}.required";

					continue;
				}
			}

			if (!$required && !Validation::required($value)) {
				continue;
			}

			foreach ($rules as $method => $arg) {
				if ($method === 'required') {
					continue;
				}

				if (!self::callRule($method, $value, $arg)) {
					$invalid[$name] = "{$prefix}.{$name}.{$method}";

					break;
				}
			}
		}

		return $invalid;
	}

	private static function callRule(string $method, mixed $value, mixed $arg): bool {
		if (!method_exists(Validation::class, $method)) {
			throw new \InvalidArgumentException("Unknown validation rule: {$method}");
		}

		if ($arg === true) {
			return Validation::$method($value);
		}

		$reflection = new ReflectionMethod(Validation::class, $method);
		$parameters = $reflection->getParameters();

		if (count($parameters) <= 1) {
			return Validation::$method($value);
		}

		$secondType = $parameters[1]->getType();
		$expectsArray = $secondType instanceof ReflectionNamedType && $secondType->getName() === 'array';

		if (is_array($arg) && !$expectsArray) {
			return Validation::$method($value, ...$arg);
		}

		return Validation::$method($value, $arg);
	}
}
