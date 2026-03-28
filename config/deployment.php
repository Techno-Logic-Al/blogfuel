<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Browser Deployment Helper
    |--------------------------------------------------------------------------
    |
    | This temporary route exists for shared hosting setups where no terminal
    | access is available. Enable it only during deployment, protect it with
    | a strong token, and disable it again immediately after launch.
    |
    */

    'enabled' => (bool) env('BLOGFUEL_DEPLOYMENT_ENABLED', false),
    'token' => env('BLOGFUEL_DEPLOYMENT_TOKEN'),

];
