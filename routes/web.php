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
    return view('welcome');
});

Route::get('/videos/test', 'Videos@test')->name('Videos');


Route::get('/videos/downloadGateway', 'Videos@downloadGateway')->name('Videos');


Route::post('/videos/displayVideosInfo', 'Videos@displayVideosInfo')->name('Videos');
Route::post('/videos/downloadSingleVideoByFormat', 'Videos@downloadSingleVideoByFormat')->name('Videos');
