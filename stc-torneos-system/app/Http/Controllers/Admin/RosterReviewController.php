<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RosterReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $categories = Category::query()
            ->accessibleTo($user)
            ->with('tournament')
            ->withCount('teams')
            ->orderBy('birth_year')
            ->orderBy('name')
            ->get();

        return view('admin.review.index', compact('categories'));
    }

    public function category(Request $request, Category $category): View
    {
        $this->assertCategoryAccess($category);

        $category->load(['tournament', 'teams' => fn ($query) => $query->with('delegation')->withCount('players')])
            ->loadCount('teams');

        return view('admin.review.category', compact('category'));
    }

    public function team(Request $request, Category $category, Team $team): View
    {
        $this->assertCategoryAccess($category);
        abort_unless((int) $team->category_id === (int) $category->id, 404);
        abort_unless($request->user()?->canAccessTournament((int) $team->tournament_id), 403, 'Este equipo no está dentro de tu alcance.');

        $team->load(['players.guardian', 'players.documents', 'delegation']);

        return view('admin.review.team', compact('category', 'team'));
    }

    private function assertCategoryAccess(Category $category): void
    {
        abort_unless(
            auth()->user()?->canAccessTournament((int) $category->tournament_id),
            403,
            'Esta categoría no está dentro de tu alcance.'
        );
    }
}
