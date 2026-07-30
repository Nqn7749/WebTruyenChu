<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Comment;
use App\Models\Story;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'storyCount' => Story::count(),
            'chapterCount' => Chapter::count(),
            'userCount' => User::count(),
            'commentCount' => Comment::count(),
        ]);
    }
}