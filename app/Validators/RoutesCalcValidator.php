<?php
namespace App\Validators;

use Respect\Validation\Validator as v;

class RoutesCalcValidator extends RequestValidator
{
    public function __construct()
    {
        $this->setRules([
            'address' => v::arrayVal()->each(v::key('id', v::stringType()->notEmpty())),
            'airport' => v::key('id', v::intVal()->positive()),
        ]);
    }
}
