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
            case 'quotesCreate':
                return new QuotesCreateValidator();
            case 'quotesRead':
                return new QuotesReadValidator();

            default:
                throw new InvalidArgumentException(400, "Validator '{$name}' is missing.");
        }
    }
}
