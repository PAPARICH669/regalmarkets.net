<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    public function index()
    {
        return Announcement::latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:191'],
            'body'      => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);
        $data['published_at'] = now();
        return response()->json(Announcement::create($data), 201);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'title'     => ['sometimes', 'string', 'max:191'],
            'body'      => ['sometimes', 'string'],
            'is_active' => ['boolean'],
        ]);
        $announcement->update($data);
        return response()->json($announcement);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return response()->json(['message' => 'Announcement deleted.']);
    }
}
