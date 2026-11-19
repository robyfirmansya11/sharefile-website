<?php

namespace App\Http\Controllers;

use App\Models\FileActivity;
use Illuminate\Http\Request;

class FileActivityController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['action' => 'nullable|in:folder,file,rename,copy,move,delete,restore,purge,upload,download,preview', 'page' => 'nullable|integer|min:1']);
        $query = FileActivity::where('actor_key', FileActivity::actorKey())->where('root_key', FileActivity::rootKey());
        if (! empty($data['action'])) $query->where('action', $data['action']);

        return response()->json($query->orderByDesc('id')->paginate(20, ['id', 'actor_name', 'action', 'details', 'status', 'http_status', 'created_at']))->header('Cache-Control', 'private, no-store');
    }
}
