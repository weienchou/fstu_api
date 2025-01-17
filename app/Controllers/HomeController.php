<?php
namespace App\Controllers;

use Sabre\HTTP\Sapi;

class HomeController extends Controller
{
    public function __construct(public Sapi $sapi)
    {}

    public function index(): void
    {
        $request = $this->sapi->getRequest();

        response()->success(['version' => env('APP_VERSION')])->send();
    }
}
