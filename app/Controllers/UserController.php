<?php
namespace App\Controllers;

class UserController extends Controller
{
    public function favorite_address()
    {
        return response()->json([
            'status' => 'success',
            'data' => [],
        ]);
    }
}
