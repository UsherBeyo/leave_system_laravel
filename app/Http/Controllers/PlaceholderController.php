<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PlaceholderController extends Controller
{
    public function __invoke(string $title): View
    {
        return view('placeholders.page', [
            'title' => ucwords(str_replace('-', ' ', $title)),
        ]);
    }
}
