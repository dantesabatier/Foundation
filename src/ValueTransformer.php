<?php

namespace Sabatier\Foundation;

/**
 * An abstract class used to transform values from one representation to another.
 */
abstract class ValueTransformer extends ObjectClass
{
    /** @var Dictionary<ValueTransformer>|null */
    private static ?Dictionary $valueTransformers = null;

    /**
     * @return Dictionary<ValueTransformer>
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement, InvalidPropertyAssignmentValue
     */
    private static function valueTransformers(): Dictionary
    {
        static::$valueTransformers ??= new Dictionary([
            NegateBooleanTransformerName => new NegateBooleanTransformer(),
            UnarchiveFromDataTransformerName => new UnarchiveFromDataTransformer(),
            SecureUnarchiveFromDataTransformerName => new UnarchiveFromDataTransformer()
        ]);
        return static::$valueTransformers;
    }

    /**
     * Registers the provided value transformer with a given identifier.
     * @param ValueTransformer $transformer The transformer to register.
     * @param string $name The name for transformer.
     */
    public static function setValueTransformerForName(ValueTransformer $transformer, string $name): void
    {
        self::valueTransformers()[$name] = $transformer;
    }

    /**
     * Returns the value transformer identified by a given identifier.
     *
     * If valueTransformerForName() does not find a registered transformer instance for name, it will attempt to find a class with the specified name. If a corresponding class is found, an instance will be created and initialized, then automatically registered with $name.
     * @param string $name The transformer identifier.
     * @return ValueTransformer|null The value transformer identified by name in the shared registry, or nil if not found.
     */
    public static function valueTransformerForName(string $name): ?ValueTransformer
    {
        if (!($transformer = self::valueTransformers()->valueForKey($name)) && class_exists($name) && is_subclass_of($name, ValueTransformer::class)) {
            /** @psalm-suppress UnsafeInstantiation */
            $transformer = new $name();
            self::setValueTransformerForName($transformer, $name);
        }
        return $transformer;
    }

    /**
     * Returns an array of all the registered value transformers.
     * @return ArrayClass<string> An array of all the registered value transformers.
     */
    public static function valueTransformerNames(): ArrayClass
    {
        return self::valueTransformers()->keys;
    }

    /**
     * Returns a Boolean value that indicates whether the receiver can reverse a transformation.
     *
     * Subclasses should override this method to return false if they do not support reverse value transformations.
     * @return bool true if the receiver supports reverse value transformations, otherwise false.
     * The default is true.
     */
    public static function allowsReverseTransformation(): bool
    {
        return true;
    }

    /**
     * Returns the class of the value returned by the receiver for a forward transformation.
     *
     * A subclass should override this method to return the appropriate class.
     * @return class-string The class of the value returned by the receiver for a forward transformation.
     * @psalm-suppress InvalidReturnType
     */
    public static function transformedValueClass(): string
    {
        request_concrete_implementation(static::class, __FUNCTION__);
    }

    /**
     * Returns the result of transforming a given value.
     *
     * A subclass should override this method to transform and return an object based on value.
     * @param mixed $value The value to transform.
     * @return mixed The result of transforming value.
     * The default implementation simply returns $value.
     */
    public function transformedValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Returns the result of the reverse transformation of a given value.
     *
     * The default implementation raises an exception if {@see allowsReverseTransformation()} returns false; otherwise it will invoke {@see transformedValue()} with value.
     * A subclass should override this method if they require a reverse transformation that is different from simply reapplying the original transform (as would be the case with negation, for example). For example, if a value transformer converts a value in Fahrenheit to Celsius, this method would convert a value from Celsius to Fahrenheit.
     * @param mixed $value The value to reverse transform.
     * @return mixed The reverse transformation of value.
     */
    public function reverseTransformedValue(mixed $value): mixed
    {
        if (!static::allowsReverseTransformation()) {
            fatal_error();
        }
        return $this->transformedValue($value);
    }
}
