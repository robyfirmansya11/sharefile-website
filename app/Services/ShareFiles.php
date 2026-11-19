<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class ShareFiles
{
    public function root(): string
    {
        if (config('share.driver') === 'windows') {
            app(WindowsIdentity::class)->principal(request());
            $root = rtrim((string) config('share.windows.root'), '\\/');
            // UNC only: using D:\\SMB would bypass the Share ACL.
            abort_unless(preg_match('/^\\\\\\\\[^\\\\\/]+\\\\[^\\\\\/]+$/', $root) === 1, 503, 'SMB_UNC_ROOT harus berupa \\\\SERVER\\Share tanpa subfolder.');
            clearstatcache(true);
            abort_unless(is_dir($root) && is_readable($root) && ! is_link($root), 403, __('Share tidak tersedia atau akun Windows Anda tidak memiliki akses.'));
            $resolved = realpath($root);
            abort_unless($resolved !== false, 403, __('Lokasi share tidak dapat diakses.'));

            return $resolved;
        }
        abort_unless(app()->environment(['local', 'testing']) && config('share.driver') === 'local', 503, 'Akses produksi membutuhkan gateway Windows dengan identitas AD setiap user.');
        $root = config('share.root');
        File::ensureDirectoryExists($root);

        return realpath($root);
    }

    public function assertWritesAllowed(): void
    {
        abort_if(config('share.driver') === 'windows' && ! config('share.windows.writes_enabled'), 403, __('Share masih dalam mode uji baca. Operasi perubahan belum diaktifkan administrator.'));
        $this->root();
    }

    public function name(string $name): string
    {
        abort_if(strcasecmp($name, RecycleBin::DIRECTORY) === 0, 403, __('Folder internal Recycle Bin tidak dapat diakses langsung.'));
        if ($name === '' || strlen($name) > 200 || preg_match('/[\x00-\x1f<>:"\/\\\\|?*]/', $name) || preg_match('/[. ]$/', $name) || preg_match('/^(con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\.|$)/i', $name)) {
            throw ValidationException::withMessages(['name' => __('Nama file/folder tidak valid.')]);
        }

        return $name;
    }

    public function path(string $path): string
    {
        $root = $this->root();
        $current = $root;
        if ($path === '') {
            return $root;
        }
        foreach (explode('/', $path) as $part) {
            $this->name($part);
            $current .= DIRECTORY_SEPARATOR.$part;
            abort_if(is_link($current), 403, __('Symbolic link tidak diizinkan.'));
            $real = realpath($current);
            abort_unless($real !== false && str_starts_with(strtolower($real), strtolower($root.DIRECTORY_SEPARATOR)), 404, __('File/folder tidak ditemukan.'));
            $relative = substr($real, strlen($root) + 1);
            abort_if(in_array(strtolower(RecycleBin::DIRECTORY), array_map('strtolower', explode(DIRECTORY_SEPARATOR, $relative)), true), 403, __('Folder internal Recycle Bin tidak dapat diakses langsung.'));
        }

        return $current;
    }

    public function destination(string $parent, string $name): string
    {
        $directory = $this->path($parent);
        abort_unless(is_dir($directory), 422, __('Tujuan harus berupa folder.'));
        $target = $directory.DIRECTORY_SEPARATOR.$this->name($name);
        abort_if(file_exists($target) || is_link($target), 409, __('Nama sudah ada. File tidak ditimpa.'));

        return $target;
    }

    public function inspectTree(string $path): void
    {
        abort_if(is_link($path), 403, __('Symbolic link tidak diizinkan.'));
        $real = realpath($path);
        abort_unless($real !== false && str_starts_with(strtolower($real), strtolower($this->root().DIRECTORY_SEPARATOR)), 403, __('Lokasi di luar share tidak diizinkan.'));
        if (is_dir($path)) {
            foreach (new \FilesystemIterator($path) as $entry) {
                $this->inspectTree($entry->getPathname());
            }
        }
    }
}
