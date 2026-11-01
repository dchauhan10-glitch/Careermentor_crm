<?php

namespace App\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Copies every CRM table into one compressed file (one line per row) and
 * loads it back. Works the same on MySQL and on any future server, and does
 * not depend on mysqldump being installed on shared hosting.
 */
final class DatabaseDumper
{
    /** Tables that are temporary and not worth backing up. */
    private const SKIP = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];

    /** @return array<string,int> rows per table */
    public static function dump(string $gzPath, ?string $connection = null): array
    {
        $db = DB::connection($connection);
        $gz = gzopen($gzPath, 'wb6');
        $counts = [];
        foreach (self::tables($connection) as $table) {
            $n = 0;
            $key = Schema::connection($connection)->hasColumn($table, 'id') ? 'id' : null;
            $q = $db->table($table);
            $key ? $q->orderBy($key) : $q->orderBy(Schema::connection($connection)->getColumnListing($table)[0]);
            foreach ($q->lazy(1000) as $row) {
                gzwrite($gz, json_encode(['t' => $table, 'r' => (array) $row], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                $n++;
            }
            $counts[$table] = $n;
        }
        gzwrite($gz, json_encode(['meta' => ['counts' => $counts, 'created_at' => now()->toIso8601String(), 'app' => 'careermentors-crm', 'format' => 1]])."\n");
        gzclose($gz);

        return $counts;
    }

    /**
     * Loads a dump into a database whose tables already exist and are empty
     * (run migrations first). Returns [loaded counts, counts recorded in the file].
     */
    public static function load(string $gzPath, string $connection): array
    {
        $db = DB::connection($connection);
        $gz = gzopen($gzPath, 'rb');
        $loaded = [];
        $meta = null;
        $buffer = [];
        $flush = function () use (&$buffer, $db) {
            foreach ($buffer as $table => $rows) {
                $db->table($table)->insert($rows);
            }
            $buffer = [];
        };

        Schema::connection($connection)->disableForeignKeyConstraints();
        try {
            while (($line = gzgets($gz)) !== false) {
                $rec = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (isset($rec['meta'])) {
                    $meta = $rec['meta'];

                    continue;
                }
                $buffer[$rec['t']][] = $rec['r'];
                $loaded[$rec['t']] = ($loaded[$rec['t']] ?? 0) + 1;
                if (count($buffer[$rec['t']]) >= 200) {
                    $flush();
                }
            }
            $flush();
        } finally {
            gzclose($gz);
            Schema::connection($connection)->enableForeignKeyConstraints();
        }
        if (! $meta) {
            throw new RuntimeException('Backup file has no summary line; it may be cut short.');
        }

        return [$loaded, $meta['counts']];
    }

    public static function tables(?string $connection = null): array
    {
        $schema = Schema::connection($connection);
        // Only this CRM's own database, never other databases on the same hosting account.
        $names = $schema->getTableListing($schema->getCurrentSchemaName(), false);

        return array_values(array_diff($names, self::SKIP));
    }
}
