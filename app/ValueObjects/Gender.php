<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use MichaelRavedoni\LaravelValueObjects\ValueObjects\BaseValueObject;

class Gender extends BaseValueObject
{
    /**
     * The raw value of the value object.
     */
    protected mixed $value;

    /**
     * Create a new Gender instance.
     *
     * @param  mixed  $value  The raw value.
     */
    public function __construct(mixed $value)
    {
        // Add your validation and logic here
        // For example:
        // if (!is_string($value) || empty($value)) {
        //     throw new InvalidArgumentException('Value cannot be empty.');
        // }
        $this->value = $value;
    }

    /**
     * Get the raw value of the value object.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return match ($this->value) {
            'm'     => 'Homme',
            'w'     => 'Femme',
            default => 'Non spécifié',
        };
    }
}
