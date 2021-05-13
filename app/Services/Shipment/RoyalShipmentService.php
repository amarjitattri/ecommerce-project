<?php
namespace App\Services\Payment;

use GuzzleHttp\Client;

// class RoyalShipmentService implements ShipmentContracts
class RoyalShipmentService
{
    public static function create()
    {
        echo '<pre>';

        $client = new Client;
        $result = $client->request('POST', 'https://api.royalmail.net/shipping/v3/token', [

            'headers' => [
                'accept' => 'application/json',
                'x-ibm-client-id' => '5da81159-bd47-459f-b2f9-4993f09ac003',
                'x-ibm-client-secret' => 'qR6eP0nQ6kO1cN1dG2dT5lM1yY0gE2mW2uP3bU8mY5wQ8qI3nS',
                'x-rmg-security-password' => 'WEM2020API',
                'x-rmg-security-username' => '0803139001API',
            ],
        ]);

        echo '<pre>';
        print_r($result);
        die;
    }
}
