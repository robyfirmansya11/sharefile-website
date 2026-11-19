<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileActivity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public static function actorKey(): string
    {
        return hash('sha256', config('share.driver').':'.auth()->id());
    }

    public static function rootKey(): string
    {
        $root = config('share.driver') === 'windows' ? config('share.windows.root') : config('share.root');

        return hash('sha256', config('share.driver').':'.strtolower(rtrim(str_replace('\\', '/', $root), '/')));
    }
}
