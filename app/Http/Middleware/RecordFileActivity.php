<?php

namespace App\Http\Middleware;

use App\Models\FileActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordFileActivity
{
    public function handle(Request $request, Closure $next)
    {
        $action = match ($request->path()) {
            'share/operations', 'share/trash' => $request->isMethod('POST') ? $request->input('action') : null,
            'share/upload' => $request->isMethod('POST') ? 'upload' : null,
            'share/download' => 'download',
            'share/preview' => 'preview',
            default => null,
        };
        if ($action === null) return $next($request);
        $action = is_string($action) && in_array($action, ['folder', 'file', 'rename', 'copy', 'move', 'delete', 'restore', 'purge', 'upload', 'download', 'preview'], true) ? $action : 'unknown';
        $details = [];
        foreach (['path', 'name', 'id'] as $key) {
            if (is_string($request->input($key))) $details[$key] = mb_substr($request->input($key), 0, 2000);
        }
        $sources = $request->input('sources', []);
        if (is_array($sources)) $details['sources'] = array_map(fn ($path) => mb_substr($path, 0, 2000), array_slice(array_values(array_filter($sources, 'is_string')), 0, 100));
        if ($action === 'upload') {
            $uploads = $request->file('files', []);
            $details['files'] = is_array($uploads) ? array_map(fn ($file) => mb_substr($file->getClientOriginalName(), 0, 200), array_slice(array_filter($uploads, fn ($file) => $file instanceof \Illuminate\Http\UploadedFile), 0, 20)) : [];
        }
        // No passwords, CSRF tokens, file contents, full request dumps or absolute roots.
        // Save the attempt before an operation so DB failure does not silently disable logging.
        $activity = FileActivity::create([
            'actor_key' => FileActivity::actorKey(), 'actor_name' => mb_substr((string) $request->user()->name, 0, 255),
            'root_key' => FileActivity::rootKey(), 'action' => $action, 'details' => $details, 'status' => 'processing',
        ]);
        try {
            $response = $next($request);
            $code = $response->getStatusCode();
            $this->finish($activity, $request, $code, $code < 400 ? ($action === 'download' ? 'prepared' : 'success') : 'failed');

            return $response;
        } catch (Throwable $exception) {
            $code = $exception instanceof ValidationException ? 422 : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            $this->finish($activity, $request, $code, 'failed');
            throw $exception;
        }
    }

    private function finish(FileActivity $activity, Request $request, int $code, string $status): void
    {
        try {
            $details = $activity->details;
            if ($request->attributes->has('activity_original_path')) $details['original_path'] = $request->attributes->get('activity_original_path');
            $activity->update(['details' => $details, 'status' => $status, 'http_status' => $code]);
        } catch (Throwable $exception) {
            // Preserve the real operation result. The existing 'processing' row signals an unresolved result.
            Log::error('File activity result could not be saved.', ['activity_id' => $activity->id]);
        }
    }
}
