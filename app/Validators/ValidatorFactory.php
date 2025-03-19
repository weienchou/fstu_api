<?php
namespace App\Validators;

use App\Exceptions\InvalidArgumentException;

class ValidatorFactory
{
    public static function getValidator(string $name): RequestValidator
    {
        switch ($name) {
            case 'routesCalc':
                return new RoutesCalcValidator();

            default:
                throw new InvalidArgumentException(400, "Validator '{$name}' is missing.");
        }
    }
}
