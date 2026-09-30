<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

class LegalController extends Controller
{
    public function terms(): View
    {
        return view('layouts.app', [
            'title' => 'Termos — VerifyRadar',
            'slot' => new HtmlString(view('legal.terms')->render()),
        ]);
    }

    public function privacy(): View
    {
        return view('layouts.app', [
            'title' => 'Privacidade — VerifyRadar',
            'slot' => new HtmlString(view('legal.privacy')->render()),
        ]);
    }
}
