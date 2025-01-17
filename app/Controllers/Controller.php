<?php
namespace App\Controllers;

use App\Libraries\DpopHandler;
use App\Libraries\Request;

class Controller
{
    public function __construct(protected Request $request, protected DpopHandler $dpopHandler)
    {}
}
