<?php
namespace App\Controllers;

use App\Services\GoogleMapsService;

class PlaceController extends Controller
{
    public function search()
    {
        try {
            $request = $this->request->json();
            $address = $request['address'];

            if (empty($address)) {
                return response()->json([
                    'status' => 'error',
                    'message' => '地址不能為空',
                ], 422);
            }
            $mapsService = new GoogleMapsService();
            $result = $mapsService->place_suggestions($address);

            response()->success(['suggestions' => $result])->send();
        } catch (GoogleMapsException $e) {
            response()->error(500, '地址查詢失敗，請稍後再試')->send();

            \Leaf\DevTools::console('Google Maps API Error: ' . $e->getMessage());
        }
    }
}
