<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        return view('search', [
            'query' => $request->query('q', ''),
            // Search only makes sense for provider-backed categories.
            'categories' => collect(config('finfolio.categories'))
                ->filter(fn ($c) => $c['searchable'] ?? false)
                ->all(),
        ]);
    }
}
