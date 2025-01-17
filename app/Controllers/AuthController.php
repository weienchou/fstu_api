<?php
namespace App\Controllers;

use App\Models\User;
use App\Services\LineService;

class AuthController extends Controller
{
    public function line_login(): void
    {
        $request = $this->request->json();

        $lineService = new LineService();
        $profile = $lineService->verifyAndGetProfile($request['token']);

        $new_user = User::processLineLogin($profile);

        $dpop_proof = f3()->get('HEADERS.Dpop');
        $token = $this->dpopHandler->createAccessToken([
            'sub' => $new_user['id'],
            'username' => $new_user['name'],
            'type' => 'access',
        ], $dpop_proof);

        response()->success(['access_token' => $token])->send();
    }
}
