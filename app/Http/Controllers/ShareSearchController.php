<?php

namespace App\Http\Controllers;

use App\Services\RecycleBin;
use App\Services\ShareFiles;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShareSearchController extends Controller
{
    public function __invoke(Request $request, ShareFiles $files)
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'path' => ['nullable', 'string', 'max:2000']]);
        $base = $data['path'] ?? '';
        $root = $files->path($base);
        abort_unless(is_dir($root) && is_readable($root), 403, __('Folder tidak dapat dibaca.'));
        $pending = [$base];
        $entries = [];
        $scanned = 0;
        $partial = false;
        $deadline = microtime(true) + 3;
        $limit = max(1, min(100, (int) config('share.search_results', 100)));
        while ($pending) {
            $parent = array_pop($pending);
            try {
                $iterator = new \FilesystemIterator($files->path($parent));
                foreach ($iterator as $item) {
                    if (++$scanned > 5000 || microtime(true) >= $deadline || count($entries) >= $limit) {
                        $partial = true;
                        break 2;
                    }
                    if ($item->isLink() || strcasecmp($item->getFilename(), RecycleBin::DIRECTORY) === 0) continue;
                    $relative = ltrim($parent.'/'.$item->getFilename(), '/');
                    try {
                        $resolved = $files->path($relative);
                        if (! is_readable($resolved)) { $partial = true; continue; }
                        $directory = is_dir($resolved);
                        if ($directory) $pending[] = $relative;
                        if (mb_stripos($item->getFilename(), $data['q']) !== false) {
                            $entries[] = ['name' => $item->getFilename(), 'path' => $relative, 'directory' => $directory,
                                'size' => $directory ? null : $item->getSize(), 'modified' => date(DATE_ATOM, $item->getMTime())];
                        }
                    } catch (HttpException | ValidationException | \RuntimeException $exception) {
                        $partial = true;
                    }
                }
            } catch (HttpException | \UnexpectedValueException $exception) {
                $partial = true;
            }
        }
        return response()->json(['entries' => $entries, 'partial' => $partial])->header('Cache-Control', 'no-store');
    }
}
