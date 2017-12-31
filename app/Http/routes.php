<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/hello', function(){
    return "Hello World :)";
});

use Illuminate\Support\Facades\Storage;
Route::get('/data', function(){
	try{
		$contents = Storage::get('cmd.log');
	} catch(Exception $e){
		$contents = $e->getMessage();	
	}
    return $contents;
});