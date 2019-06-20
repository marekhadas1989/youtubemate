<?php

/*
 * download from txt
 *
https://www.youtube.com/watch?v=SDTZ7iX4vTQ
https://www.youtube.com/watch?v=6FEDrU85FLE
https://www.youtube.com/watch?v=YgSPaXgAdzE
youtube-dl --restrict-filenames -o "/mmc128gb/%(title)s.%(ext)s" --batch-file='test.txt'
 */
function va($p){
    echo '<pre>';
    print_r($p);
    echo '</pre>';
}
/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

define('LARAVEL_START', microtime(true));

set_time_limit(0);

exec('youtube-dl -g https://www.youtube.com/watch?v=9OFpfTd0EIs&list=RD1lyu1KKwC74&index=26',$output);

function getFileSize($url){

    $ch = curl_init( $url );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_NOBODY, TRUE);

    $data = curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

    curl_close($ch);

    //returns file size in MB
    echo round($size/1024/1024,2);
}

function downloadFile($file_to_download,$file_name){

    file_put_contents( 'progress.txt', '' );

    $targetFile = fopen( $file_name, 'w' );
    $ch = curl_init( $file_to_download );

    curl_setopt( $ch, CURLOPT_PROGRESSFUNCTION, 'progressCallback' );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt( $ch, CURLOPT_NOPROGRESS, false );

    curl_setopt( $ch, CURLOPT_FILE, $targetFile );
    curl_exec( $ch );

    fclose( $targetFile );

}

function progressCallback ($resource, $download_size, $downloaded_size, $upload_size, $uploaded_size)
{

    static $previousProgress = 0;

    if ($download_size == 0) {
        $progress = 0;
    }else{
        $progress = round($downloaded_size * 100 / $download_size, 2);
    }


    if ( $progress > $previousProgress)
    {
        $previousProgress = $progress;
        file_put_contents('progress2.txt', $progress);

    }

}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our application. We just need to utilize it! We'll simply require it
| into the script here so that we don't have to worry about manual
| loading any of our classes later on. It feels great to relax.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Turn On The Lights
|--------------------------------------------------------------------------
|
| We need to illuminate PHP development, so let us turn on the lights.
| This bootstraps the framework and gets it ready for use, then it
| will load up this application so that we can run it and send
| the responses back to the browser and delight our users.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request
| through the kernel, and send the associated response back to
| the client's browser allowing them to enjoy the creative
| and wonderful application we have prepared for them.
|
*/

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
