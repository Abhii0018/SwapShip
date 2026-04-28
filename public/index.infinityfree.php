<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| InfinityFree deployment entrypoint template
|--------------------------------------------------------------------------
|
| Put your Laravel app files OUTSIDE public_html/htdocs, for example:
|   /home/volXX_X/infinityfree.com/your-app/
|
| Keep only public files inside public_html/htdocs.
| Then set APP_BASE_PATH below to that app folder path.
|
*/

$appBasePath = '/home/your-account/your-app';

if (file_exists($maintenance = $appBasePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appBasePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appBasePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
