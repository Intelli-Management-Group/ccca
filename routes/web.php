<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AlbumCategoryController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ImageController;

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

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('/', [HomeController::class, 'index'])->name('Home');

Route::get('/Home', [HomeController::class, 'index'])->name('Home');

Route::get('/About', function () {
    return view('frontend.about');
})->name('About');
Route::get('/GetInvolved', function () {
    return view('frontend.getinvolved');
})->name('GetInvolved');
Route::get('/Contact', function () {
    return view('frontend.contact');
})->name('Contact');
//Route::get('/aboutus', 'HomeController@aboutus')->name('aboutus');

Route::get('/news-list', [HomeController::class, 'newsList'])->name('news-list');

Route::get('/news-detail/{id}', [HomeController::class, 'newsDetail'])->whereNumber('id')->name('news-detail');

Route::get('/archives/{year}', [HomeController::class, 'getArchiveNews'])->whereNumber('year')->name('news-archive');

Route::get('/gallery-list', [HomeController::class, 'galleryList'])->name('gallery-list');

Route::get('/gallery-detail/{id}', [HomeController::class, 'galleryDetail'])->whereNumber('id')->name('gallery-detail');


// Public self-registration is disabled: admin accounts are created by an administrator.
Auth::routes(['register' => false]);

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    Route::resource('news', NewsController::class);
    Route::resource('category', CategoryController::class);
    Route::resource('albumcategory', AlbumCategoryController::class);

    Route::get('/albums', [AlbumController::class, 'getList'])->name('albums');
    Route::get('albums/createalbum', [AlbumController::class, 'getForm'])->name('create_album_form');
    Route::post('albums/createalbum', [AlbumController::class, 'postCreate'])->name('create_album');
    Route::delete('/deletealbum/{id}', [AlbumController::class, 'getDelete'])->whereNumber('id')->name('delete_album');
    Route::get('/albums/view-album/{id}', [AlbumController::class, 'getAlbum'])->whereNumber('id')->name('show_album');
    Route::get('/albums/edit-album/{id}', [AlbumController::class, 'editAlbum'])->whereNumber('id')->name('edit_album');
    Route::post('albums/edit-album', [AlbumController::class, 'updateAlbum'])->name('update_album');

    Route::get('/addimage/{id}', [ImageController::class, 'getForm'])->whereNumber('id')->name('add_image');
    Route::post('/addimage', [ImageController::class, 'postAdd'])->name('add_image_to_album');
    Route::delete('/deleteimage/{id}', [ImageController::class, 'getDelete'])->whereNumber('id')->name('delete_image');
    Route::post('/moveimage', [ImageController::class, 'postMove'])->name('move_image');
});
