<?php

namespace App\Http\Controllers;

use App\Services\RecycleBin;
use App\Services\ShareFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RecycleBinController extends Controller
{
    public function index(RecycleBin $bin)
    {
        return Cache::lock('share-mutations', 300)->block(10, fn () => response()->json(['entries' => $bin->entries()]));
    }

    public function update(Request $request, RecycleBin $bin, ShareFiles $files)
    {
        $files->assertWritesAllowed();
        $data = $request->validate(['id' => 'required|uuid', 'action' => 'required|in:restore,purge', 'confirm' => 'exclude_unless:action,purge|required|accepted']);

        return Cache::lock('share-mutations', 300)->block(10, function () use ($bin, $data) {
            if ($data['action'] === 'restore') $bin->restore($data['id']);
            else $bin->purge($data['id']);

            return response()->json(['message' => $data['action'] === 'restore' ? __('Item berhasil dipulihkan ke lokasi asal.') : __('Item telah dihapus permanen.')]);
        });
    }
}
