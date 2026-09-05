<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminLanguageController extends Controller
{
    public function switch(Request $request)
    {
        $locale = $request->input('locale');

        if (in_array($locale, ['en', 'ar'])) {
            session(['admin_locale' => $locale]);
        }

        return redirect()->back();
    }
}
