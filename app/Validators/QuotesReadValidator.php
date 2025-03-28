<?php
namespace App\Validators;

use Respect\Validation\Validator as v;

class QuotesReadValidator extends RequestValidator
{
    public function __construct()
    {
        $this->setRules([
            'token' => v::stringType()->notEmpty(),
        ]);
    }
}
