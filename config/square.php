<?php
return [
    'access_token' => env('SQUARE_ACCESS_TOKEN'),
    'application_id' => env('SQUARE_APPLICATION_ID'),
    'location_id' => env('SQUARE_LOCATION_ID'),
    'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
];
