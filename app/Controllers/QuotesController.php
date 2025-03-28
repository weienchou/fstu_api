<?php
namespace App\Controllers;

use App\Enums\AirportEnum;
use App\Services\FareCalculationService;
use App\Services\GoogleMapsService;
use App\Validators\ValidatorFactory;
use Bayfront\ArrayHelpers\Arr;
use Exception;

class QuotesController extends Controller
{
    public function create()
    {
        try {
            $request = $this->request->json();

            $validator = ValidatorFactory::getValidator('quotesCreate');
            $validated_data = $validator->validate($request);

            $airport = AirportEnum::fromId($validated_data['airport']['id']);

            $mapsService = new GoogleMapsService();
            $result = $mapsService->calculateRouteToAirport($validated_data['address'][0]['id'], $airport->getPlaceID());

            $fareService = new FareCalculationService();
            $breakdown = $fareService->calculateFareFromPolyline($result['encodedPolyline'], $result['distance'], $result['duration']);

            response()->success(['token' => $breakdown['calculation_token']])->send();
        } catch (Exception $e) {
            response()->error(500, 'Calculating fare failed')->send();

            // \Leaf\DevTools::console('Google Maps API Error: ' . $e->getMessage());
        }
    }

    public function update()
    {
        try {
            $request = $this->request->json();

            response()->success()->send();
        } catch (Exception $e) {
            // response()->error(500, '地址查詢失敗，請稍後再試')->send();

            // \Leaf\DevTools::console('Google Maps API Error: ' . $e->getMessage());
        }
    }

    public function read()
    {
        try {
            $request = $this->request->params();

            $validator = ValidatorFactory::getValidator('quotesRead');
            $validated_data = $validator->validate($request);

            $fareService = new FareCalculationService();
            $breakdown = $fareService->getCalculationFromRedis($validated_data['token']);

            response()->success(Arr::only($breakdown, [
                'base_fare', 'calculation_token', 'distance_cost', 'early_bird_discount', 'insurance_fee', 'total_fare', 'total_toll_fee',
                'area_adjustments_fee',
            ]))->send();
        } catch (Exception $e) {
            response()->error(500, 'Get calculate result failed')->send();
            // response()->error(500, '地址查詢失敗，請稍後再試')->send();

            // \Leaf\DevTools::console('Google Maps API Error: ' . $e->getMessage());
        }
    }
}
