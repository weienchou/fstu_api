<?php
namespace App\Controllers;

use App\Enums\AirportEnum;
use App\Exceptions\BaseException;
use App\Services\GoogleMapsService;
use App\Validators\ValidatorFactory;

class RoutesController extends Controller
{
    public function calc(): void
    {
        try {
            $request = $this->request->json();

            $validator = ValidatorFactory::getValidator('routesCalc');
            $validated_data = $validator->validate($request);

            $airport = AirportEnum::fromId($validated_data['airport']['id']);

            $mapsService = new GoogleMapsService();
            $result = $mapsService->calculateRouteToAirport($validated_data['address'][0]['id'], $airport->getPlaceID());

            response()->success(['routes' => $result['encodedPolyline']])->send();

        } catch (BaseException $e) {
            response()->error(500, 'Routes generate failed')->send();
        }
    }
}
