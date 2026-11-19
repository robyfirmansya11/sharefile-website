<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RecycleBin
{
    public const DIRECTORY = '.sharefolder-trash';

    public function __construct(private ShareFiles $files) {}

    private function directory(): string
    {
        abort_unless(config('share.driver') === 'local', 503, __('Recycle Bin saat ini tersedia untuk folder lokal saja.'));
        $root = $this->files->root();
        $directory = $root.DIRECTORY_SEPARATOR.self::DIRECTORY;
        abort_if(is_link($directory), 403, __('Lokasi Recycle Bin tidak valid.'));
        File::ensureDirectoryExists($directory);
        abort_unless(realpath($directory) === $directory, 403, __('Lokasi Recycle Bin tidak valid.'));

        return $directory;
    }

    public function put(string $source): bool
    {
        $resolved = $this->files->path($source);
        abort_if($source === '', 422);
        $this->files->inspectTree($resolved);
        $id = (string) Str::uuid();
        $directory = $this->directory().DIRECTORY_SEPARATOR.$id;
        File::makeDirectory($directory);
        $metadata = [
            'id' => $id, 'owner' => (string) auth()->id(), 'name' => basename($resolved),
            'original_path' => $source, 'directory' => is_dir($resolved), 'deleted_at' => now()->toIso8601String(),
        ];
        // Write recovery information before moving. A failed move leaves the original intact.
        $json = json_encode($metadata, JSON_THROW_ON_ERROR);
        abort_unless(File::put($directory.'/metadata.json', $json) === strlen($json), 500, __('Metadata pemulihan gagal disimpan; file belum dipindahkan.'));
        abort_unless(@rename($resolved, $directory.'/payload'), 500, __('File tidak dapat dipindahkan ke Recycle Bin.'));

        return true;
    }

    private function item(string $id): array
    {
        abort_unless(Str::isUuid($id), 404);
        $directory = $this->directory().DIRECTORY_SEPARATOR.$id;
        abort_if(is_link($directory) || is_link($directory.'/metadata.json'), 403);
        abort_unless(realpath($directory) === $directory, 404);
        abort_unless(is_file($directory.'/metadata.json'), 404, __('Item Recycle Bin tidak ditemukan.'));
        $metadata = json_decode(File::get($directory.'/metadata.json'), true);
        abort_unless(is_array($metadata) && ($metadata['id'] ?? '') === $id && ($metadata['owner'] ?? '') === (string) auth()->id(), 404);
        abort_unless(is_string($metadata['original_path'] ?? null) && $metadata['original_path'] !== '', 409, __('Metadata pemulihan tidak valid.'));
        $this->files->inspectTree($directory);

        return [$directory, $metadata];
    }

    public function entries(): array
    {
        $entries = [];
        foreach (new \FilesystemIterator($this->directory()) as $entry) {
            if ($entry->isLink() || ! $entry->isDir() || ! Str::isUuid($entry->getFilename())) continue;
            try {
                [$directory, $metadata] = $this->item($entry->getFilename());
                if (file_exists($directory.'/payload')) {
                    unset($metadata['owner']);
                    $entries[] = $metadata;
                }
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                if ($exception->getStatusCode() !== 404) throw $exception;
            }
        }
        usort($entries, fn ($a, $b) => strcmp($b['deleted_at'], $a['deleted_at']));

        return $entries;
    }

    public function restore(string $id): void
    {
        [$directory, $metadata] = $this->item($id);
        request()->attributes->set('activity_original_path', $metadata['original_path']);
        $parts = explode('/', $metadata['original_path']);
        $name = array_pop($parts);
        $target = $this->files->destination(implode('/', $parts), $name);
        abort_unless(file_exists($directory.'/payload'), 404, __('Isi Recycle Bin tidak ditemukan.'));
        abort_unless(@rename($directory.'/payload', $target), 500, __('Pemulihan gagal; item tetap berada di Recycle Bin.'));
        File::delete($directory.'/metadata.json');
        @rmdir($directory);
    }

    public function purge(string $id): void
    {
        [$directory, $metadata] = $this->item($id);
        request()->attributes->set('activity_original_path', $metadata['original_path']);
        $payload = $directory.'/payload';
        if (is_dir($payload)) File::deleteDirectory($payload);
        elseif (is_file($payload)) File::delete($payload);
        clearstatcache(true);
        abort_if(file_exists($payload), 500, __('Sebagian isi belum terhapus. Muat ulang dan periksa kembali.'));
        File::delete($directory.'/metadata.json');
        @rmdir($directory);
    }
}
