<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, AdminSearch $search): View
    {
        $term = trim($request->string('search')->toString());

        return view('admin.search', [
            'term' => $term,
            'groups' => $term === '' ? [] : $search->search($request->user(), $term),
        ]);
    }

    public function suggest(Request $request, AdminSearch $search): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return response()->json(['suggestions' => []]);
        }

        return response()->json([
            'suggestions' => $search->suggestions(
                $request->user(),
                $term,
                $request->string('scope')->toString() ?: null
            ),
        ]);
    }
}
