<?php

namespace App\Backup;

interface Destination
{
    public function put(string $localPath, string $name): void;

    /** @return list<string> file names */
    public function list(): array;

    public function get(string $name, string $localPath): void;

    public function delete(string $name): void;

    public function label(): string;
}
