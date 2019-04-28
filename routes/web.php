<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $videos = new \App\Videos();

    return view('welcome',array(
        'recently_downloaded'=>$videos->getRecentlyAdded())
    );
});

Route::get('/videos/test', 'Videos@test')->name('Videos');


Route::get('/videos/downloadGateway', 'Videos@downloadGateway')->name('Videos');


Route::post('/videos/displayVideosInfo', 'Videos@displayVideosInfo')->name('Videos');
Route::post('/videos/downloadSingleVideoByFormat', 'Videos@downloadSingleVideoByFormat')->name('Videos');
Route::post('/videos/downloadPlaylist', 'Videos@downloadPlaylist')->name('Videos');

Route::get('/cron/downloadVideos', 'Cron@downloadVideos')->name('cron');
Route::get('/cron/downloadThumbnails', 'Cron@downloadThumbnails')->name('cron');


Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
Route::get('/logout', function(){
    Auth::logout();
    return Redirect::to('/');
});