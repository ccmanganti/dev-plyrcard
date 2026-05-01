<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.index')->name('marketing.home');
Route::view('/about', 'pages.about')->name('marketing.about');
Route::view('/pricing', 'pages.pricing')->name('marketing.pricing');
Route::view('/book-demo', 'pages.book-demo')->name('marketing.book-demo');
Route::view('/registration', 'pages.registration')->name('marketing.registration');
Route::view('/podcast', 'pages.podcast')->name('marketing.podcast');
