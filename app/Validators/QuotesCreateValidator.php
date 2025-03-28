<?php
namespace App\Validators;

use Respect\Validation\Validator as v;

class QuotesCreateValidator extends RequestValidator
{
    public function __construct()
    {
        $this->setRules([
            'address' => v::arrayVal()->each(v::key('id', v::stringType()->notEmpty())),
            'airport' => v::key('id', v::intVal()->positive()),
            'type' => v::stringType()->notEmpty()->in(['pick-up', 'drop-off']),
        ]);
    }
}
