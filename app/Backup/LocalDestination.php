<?php

namespace App\Backup;

use Illuminate\Support\Facades\Storage;

/** Private folder on the server itself (not reachable from the web). Used for testing and as a fallback. */
class LocalDestination implements Destination
{
    private const DIR = 'backups';

    public function put(string $localPath, string $name): void
    {
        Storage::disk('local')->putFileAs(self::DIR, new \Illuminate\Http\File($localPath), $name);
    }

    public function list(): array
    {
        return array_map('basename', Storage::disk('local')->files(self::DIR));
    }

    public function get(string $name, string $localPath): void
    {
        copy(Storage::disk('local')->path(self::DIR.'/'.$name), $localPath);
    }

    public function delete(string $name): void
    {
        Storage::disk('local')->delete(self::DIR.'/'.$name);
    }

    public function label(): string
    {
        return 'server private folder';
    }
}
