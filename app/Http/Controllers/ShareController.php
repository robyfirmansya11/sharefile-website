<?php

namespace App\Http\Controllers;

use App\Services\ShareFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ShareController extends Controller
{
    public function index(Request $request, ShareFiles $files)
    {
        $data = $request->validate(['path' => 'nullable|string|max:2000']);
        $path = $data['path'] ?? '';
        $directory = $files->path($path);
        abort_unless(is_dir($directory), 422, __('Bukan folder.'));
        $entries = [];
        foreach (new \FilesystemIterator($directory) as $entry) {
            if ($entry->isLink() || strcasecmp($entry->getFilename(), \App\Services\RecycleBin::DIRECTORY) === 0) {
                continue;
            }
            $entries[] = [
                'name' => $entry->getFilename(),
                'path' => ltrim($path.'/'.$entry->getFilename(), '/'),
                'directory' => $entry->isDir(),
                'size' => $entry->isDir() ? null : $entry->getSize(),
                'modified' => date(DATE_ATOM, $entry->getMTime()),
            ];
        }

        return response()->json(['entries' => $entries]);
    }

    public function download(Request $request, ShareFiles $files)
    {
        $data = $request->validate(['path' => 'required|string|max:2000']);
        $path = $files->path($data['path']);
        abort_unless(is_file($path), 422, __('Pilih sebuah file.'));

        return response()->download($path, basename($path), ['X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(Request $request, ShareFiles $files)
    {
        $data = $request->validate(['path' => 'required|string|max:2000']);
        $path = $files->path($data['path']);
        abort_unless(is_file($path), 422, __('Pilih sebuah file.'));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $image = in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true);
        $text = in_array($extension, ['txt', 'md', 'csv', 'json', 'log'], true);
        $pdf = $extension === 'pdf';
        abort_unless($image || $text || $pdf, 415, __('Preview format ini belum tersedia. Gunakan tombol Unduh untuk membukanya.'));
        $limit = $pdf ? 10 * 1024 * 1024 : ($image ? 5 * 1024 * 1024 : 1024 * 1024);
        abort_if(filesize($path) > $limit, 413, __('File terlalu besar untuk preview. Silakan unduh file.'));
        // Bound the read even if another process grows the file after filesize().
        $contents = file_get_contents($path, false, null, 0, $limit + 1);
        abort_if($contents === false, 403, __('File tidak dapat dibaca.'));
        abort_if(strlen($contents) > $limit, 413, __('File terlalu besar untuk preview.'));
        if ($pdf) {
            abort_unless(str_starts_with($contents, '%PDF-'), 415, __('Isi file bukan PDF yang dikenali.'));
            $result = ['kind' => 'pdf', 'data' => base64_encode($contents)];
        } elseif ($image) {
            $info = @getimagesizefromstring($contents);
            abort_unless($info && in_array($info['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true), 415, __('Isi file bukan gambar yang didukung.'));
            abort_if($info[0] * $info[1] > 40000000, 413, __('Resolusi gambar terlalu besar untuk preview.'));
            $result = ['kind' => 'image', 'source' => 'data:'.$info['mime'].';base64,'.base64_encode($contents)];
        } else {
            abort_unless(mb_check_encoding($contents, 'UTF-8') && ! str_contains($contents, "\0"), 415, __('Preview teks memerlukan file UTF-8. Silakan unduh file.'));
            $result = ['kind' => 'text', 'content' => $contents];
        }

        // Never serve uploaded HTML/SVG as active content on the application origin.
        return response()->json($result)->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function mutate(Request $request, ShareFiles $files)
    {
        $files->assertWritesAllowed();
        $data = $request->validate([
            'action' => 'required|in:folder,file,copy,move,delete,rename',
            'path' => 'nullable|string|max:2000',
            'name' => 'required_if:action,folder,file,rename|string|max:200',
            'content' => 'nullable|string|max:1000000',
            'sources' => 'required_if:action,copy,move,delete,rename|array|min:1|max:100',
            'sources.*' => 'required|string|max:2000|distinct',
        ]);
        $parent = $data['path'] ?? '';
        // Serialize app mutations to avoid check/write races between web requests.
        return \Illuminate\Support\Facades\Cache::lock('share-mutations', 300)->block(10, function () use ($data, $parent, $files) {
            if ($data['action'] === 'rename') {
                abort_unless(count($data['sources']) === 1, 422, __('Pilih satu file atau folder untuk diganti nama.'));
                $source = $data['sources'][0];
                $resolved = $files->path($source);
                $files->inspectTree($resolved);
                $files->name($data['name']);
                if (basename($resolved) !== $data['name']) {
                    $parentPath = str_contains($source, '/') ? substr($source, 0, strrpos($source, '/')) : '';
                    $target = $files->destination($parentPath, $data['name']);
                    abort_unless(File::move($resolved, $target), 500, __('Nama tidak dapat diubah. Muat ulang untuk memeriksa file.'));
                }
            } elseif (in_array($data['action'], ['file', 'folder'])) {
                $target = $files->destination($parent, $data['name']);
                if ($data['action'] === 'folder') {
                    abort_unless(File::makeDirectory($target), 500, __('Gagal membuat folder.'));
                } else {
                    $handle = @fopen($target, 'x');
                    abort_unless($handle !== false, 409, __('File tidak dapat dibuat.'));
                    try {
                        $content = $data['content'] ?? '';
                        abort_unless(fwrite($handle, $content) === strlen($content), 500, __('Gagal menulis file.'));
                    } finally {
                        fclose($handle);
                    }
                }
            } else {
                $operations = [];
                $targets = [];
                foreach ($data['sources'] as $source) {
                    $resolved = $files->path($source);
                    $files->inspectTree($resolved);
                    foreach ($data['sources'] as $other) {
                        abort_if($other !== $source && str_starts_with(strtolower($source), strtolower($other.'/')), 422, __('Pilih folder atau isinya, jangan keduanya.'));
                    }
                    $target = null;
                    if ($data['action'] !== 'delete') {
                        abort_if(strtolower($parent) === strtolower($source) || str_starts_with(strtolower($parent), strtolower($source.'/')), 422, __('Folder tidak dapat ditempel ke dalam dirinya sendiri.'));
                        $target = $files->destination($parent, basename($resolved));
                        abort_if(in_array(strtolower($target), $targets), 409, __('Ada nama tujuan yang sama.'));
                        $targets[] = strtolower($target);
                    }
                    $operations[] = [$resolved, $target];
                }
                foreach ($operations as $index => [$source, $target]) {
                    $success = match ($data['action']) {
                        'delete' => app(\App\Services\RecycleBin::class)->put($data['sources'][$index]),
                        'move' => File::move($source, $target),
                        'copy' => is_dir($source) ? File::copyDirectory($source, $target) : File::copy($source, $target),
                    };
                    abort_unless($success, 500, __('Operasi gagal; muat ulang untuk melihat hasil terakhir.'));
                }
            }

            return response()->json(['message' => __('Operasi berhasil.')]);
        });
    }

    public function upload(Request $request, ShareFiles $files)
    {
        $files->assertWritesAllowed();
        $data = $request->validate([
            'path' => 'nullable|string|max:2000',
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'required|file|max:'.config('share.max_upload_kb'),
        ]);

        return \Illuminate\Support\Facades\Cache::lock('share-mutations', 300)->block(10, function () use ($data, $files) {
            $targets = [];
            foreach ($data['files'] as $upload) {
                $target = $files->destination($data['path'] ?? '', $upload->getClientOriginalName());
                abort_if(in_array(strtolower($target), $targets), 409, __('Nama upload duplikat.'));
                $targets[] = strtolower($target);
            }
            foreach ($data['files'] as $upload) {
                $upload->move($files->path($data['path'] ?? ''), $upload->getClientOriginalName());
            }

            return response()->json(['message' => __('Upload selesai.')]);
        });
    }
}
