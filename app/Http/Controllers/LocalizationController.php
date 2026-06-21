<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocalizationController extends Controller
{
    public function getLocale()
    {
        return response()->json(Session::get('locale', config('app.locale')));
    }

    public function setLocale($locale)
    {
        $availableLocales = ['en', 'ru', 'tm'];

        if (in_array($locale, $availableLocales)) {
            App::setLocale($locale);
            Session::put('locale', $locale);
        }

        return response()->json(Session::get('locale'));
    }
}
