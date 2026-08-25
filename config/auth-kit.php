<?php

declare(strict_types=1);

use Gusmanwidodo\AuthKit\Contracts\AuthPlugin;

return [

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    | Every plugin's routes are mounted under this URI prefix, so a plugin
    | with id "otp" that defines "/send" is reachable at "/auth-kit/otp/send".
    */
    'prefix' => env('AUTH_KIT_PREFIX', 'auth-kit'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    | Middleware applied to the group that wraps all plugin routes.
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    | Explicitly register plugin instances here. Plugins distributed as
    | separate Composer packages typically self-register via their own
    | service provider, so most apps can leave this empty.
    |
    | @var array<int, AuthPlugin|class-string<AuthPlugin>>
    */
    'plugins' => [
        // Gusmanwidodo\AuthKitOtp\OtpPlugin::class,
    ],

];
