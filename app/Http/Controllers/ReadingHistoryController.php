<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class ReadingHistoryController extends Controller
{
    public function index()
    {
        $histories = Auth::user()
            ->readingHistories()
            ->with(['story', 'chapter'])
            ->latest('read_at')
            ->paginate(20);

        return view('reading-history.index', compact('histories'));
    }
}