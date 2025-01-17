<?php
namespace App\Services;

use App\Exceptions\LineException;
use App\Libraries\LineApi;

class LineService
{
    private $lineApi;

    public function __construct()
    {
        $this->lineApi = new LineApi();
    }

    public function verifyAndGetProfile(string $accessToken)
    {
        try {
            // Verify token first
            $this->lineApi->verifyAccessToken($accessToken);

            // Get user profile if token is valid
            return $this->lineApi->getUserProfile($accessToken);
        } catch (LineException $e) {
            // 直接往上拋LineException
            throw $e;
        } catch (\Exception $e) {
            // 其他未預期的錯誤
            throw new LineException(400, [], $e);
        }
    }
}
