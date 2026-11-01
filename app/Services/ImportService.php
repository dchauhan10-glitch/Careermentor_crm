<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Support\MobileNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Import Center engine. The uploaded file is first copied into a plain CSV
 * (streamed, low memory), then processed a few hundred rows at a time so a
 * 20,000-row file never hits shared-hosting time limits. Each row goes
 * through the same intake as website sign-ups, so existing records are only
 * ever added to, never overwritten.
 */
class ImportService
{
    public const BATCH_ROWS = 300;

    /** CRM field => label shown on the mapping screen. */
    public const FIELDS = [
        'name' => 'Student name *',
        'mobile' => 'Mobile *',
        'parent_mobile' => 'Parent mobile',
        'email' => 'Email',
        'course' => 'Course',
        'exam' => 'Entrance exam',
        'exam_rank' => 'Exam rank',
        'college' => 'College',
        'city_state' => 'City / state',
        'admission_year' => 'Admission year',
    ];

    private const GUESSES = [
        'name' => ['name', 'student name', 'student', 'full name', 'candidate name'],
        'mobile' => ['mobile', 'phone', 'mobile number', 'contact', 'contact number', 'phone number', 'whatsapp', 'student mobile'],
        'parent_mobile' => ['parent mobile', 'father mobile', 'parent phone', 'parents mobile', 'guardian mobile', 'father number'],
        'email' => ['email', 'e-mail', 'email id', 'mail'],
        'course' => ['course', 'course interested', 'interested course', 'stream'],
        'exam' => ['exam', 'entrance exam', 'exam interested'],
        'exam_rank' => ['rank', 'exam rank', 'air', 'score'],
        'college' => ['college', 'interested college', 'school', 'institute'],
        'city_state' => ['city', 'state', 'city/state', 'city state', 'location', 'address', 'district'],
        'admission_year' => ['year', 'admission year', 'session', 'batch'],
    ];

    /** Saves the upload privately, converts it to CSV and counts rows. */
    public function prepare(UploadedFile $file, ImportBatch $batch): void
    {
        $dir = 'imports/'.$batch->id;
        $ext = strtolower($file->getClientOriginalExtension());
        $stored = $file->storeAs($dir, 'original.'.$ext, 'local');
        $batch->stored_path = $stored;

        $reader = $ext === 'xlsx' ? new XlsxReader : new CsvReader;
        $csvPath = $dir.'/rows.csv';
        $out = fopen(Storage::disk('local')->path($csvPath), 'w');
        $headers = null;
        $count = 0;

        try {
            $reader->open(Storage::disk('local')->path($stored));
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($v) => $this->cellToString($v), $row->toArray());
                    if (implode('', $cells) === '') {
                        continue; // skip blank lines
                    }
                    if ($headers === null) {
                        $headers = array_map(fn ($h) => Str::limit(trim($h), 60, ''), $cells);
                        continue;
                    }
                    fputcsv($out, $cells, ',', '"', '');
                    $count++;
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
            fclose($out);
        }

        if (! $headers) {
            throw new InvalidArgumentException('The file looks empty.');
        }

        $batch->fill(['csv_path' => $csvPath, 'headers' => $headers, 'total_rows' => $count]);
        $batch->column_map = $this->guessMap($headers);
        $batch->save();
    }

    /** First rows, already mapped and checked, for the preview screen. */
    public function preview(ImportBatch $batch, array $map, int $rows = 10): array
    {
        $fh = fopen(Storage::disk('local')->path($batch->csv_path), 'r');
        $out = [];
        while (count($out) < $rows && ($cells = fgetcsv($fh, null, ',', '"', '')) !== false) {
            $data = $this->mapRow($cells, $map);
            $out[] = ['data' => $data, 'problem' => $this->problem($data)];
        }
        fclose($fh);

        return $out;
    }

    /** Processes the next batch of rows. Returns true when the whole file is done. */
    public function processNext(ImportBatch $batch, LeadIntakeService $intake, int $limit = self::BATCH_ROWS): bool
    {
        if (in_array($batch->status, ['completed', 'failed'], true)) {
            return true;
        }
        $batch->update(['status' => 'processing']);
        $fh = fopen(Storage::disk('local')->path($batch->csv_path), 'r');
        fseek($fh, $batch->byte_offset);
        $source = $batch->source;
        $counts = ['created_count' => 0, 'merged_count' => 0, 'error_count' => 0, 'review_count' => 0];
        $done = 0;

        try {
            while ($done < $limit && ($cells = fgetcsv($fh, null, ',', '"', '')) !== false) {
                $rowNumber = $batch->processed_rows + $done + 2; // +1 header, +1 to count from 1
                $done++;
                $data = $this->mapRow($cells, $batch->column_map);
                if ($problem = $this->problem($data)) {
                    ImportError::create(['import_batch_id' => $batch->id, 'row_number' => $rowNumber, 'message' => $problem]);
                    $counts['error_count']++;

                    continue;
                }
                try {
                    $before = \App\Models\DuplicateReview::count();
                    $res = $intake->intake($data + ['collected_on' => $batch->collected_on->toDateString()], $source,
                        ['channel' => 'import', 'import_batch_id' => $batch->id, 'created_by' => $batch->uploaded_by]);
                    $counts[$res['outcome'] === 'created' ? 'created_count' : 'merged_count']++;
                    $counts['review_count'] += \App\Models\DuplicateReview::count() - $before;
                } catch (Throwable $e) {
                    ImportError::create(['import_batch_id' => $batch->id, 'row_number' => $rowNumber,
                        'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not be saved']);
                    $counts['error_count']++;
                    report($e);
                }
            }
            $offset = ftell($fh);
            $finished = feof($fh) || fgetcsv($fh, null, ',', '"', '') === false;
        } finally {
            fclose($fh);
        }

        foreach ($counts as $k => $n) {
            $batch->{$k} += $n;
        }
        $batch->processed_rows += $done;
        $batch->byte_offset = $offset;
        if ($finished) {
            $batch->status = 'completed';
            $batch->completed_at = now();
        }
        $batch->save();

        return $finished;
    }

    public function mapRow(array $cells, array $map): array
    {
        $data = [];
        foreach ($map as $field => $col) {
            if ($col === null || $col === '' || ! array_key_exists($field, self::FIELDS)) {
                continue;
            }
            $v = trim((string) ($cells[(int) $col] ?? ''));
            if ($v !== '') {
                $data[$field] = $v;
            }
        }
        foreach (['exam_rank', 'admission_year'] as $num) {
            if (isset($data[$num])) {
                $data[$num] = ctype_digit(preg_replace('/\D/', '', $data[$num])) ? (int) preg_replace('/\D/', '', $data[$num]) : null;
            }
        }
        if (isset($data['admission_year']) && ($data['admission_year'] < 2000 || $data['admission_year'] > 2100)) {
            $data['admission_year'] = null;
        }
        if (isset($data['email']) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            unset($data['email']); // a bad email should not lose the whole student
        }

        return $data;
    }

    public function problem(array $data): ?string
    {
        if (empty($data['name'])) {
            return 'Name is missing';
        }
        if (empty($data['mobile'])) {
            return 'Mobile is missing';
        }
        if (! MobileNumber::normalize($data['mobile'])) {
            return 'Mobile "'.Str::limit($data['mobile'], 4, '…').'" is not a valid 10-digit Indian number';
        }

        return null;
    }

    private function guessMap(array $headers): array
    {
        $map = array_fill_keys(array_keys(self::FIELDS), null);
        $norm = array_map(fn ($h) => trim(preg_replace('/[^a-z\/ ]+/', ' ', strtolower($h))), $headers);
        foreach (self::GUESSES as $field => $options) {
            foreach ($norm as $i => $h) {
                if (in_array($h, $options, true) && ! in_array($i, $map, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    private function cellToString(mixed $v): string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_float($v) && floor($v) === $v && abs($v) < 1e15) {
            return number_format($v, 0, '.', ''); // mobiles stored as numbers in Excel
        }

        return trim((string) $v);
    }
}
