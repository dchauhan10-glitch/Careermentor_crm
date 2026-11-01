<?php

namespace App\Backup;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Private folder in the owner's Google Drive. Uses the "drive.file" permission,
 * so the CRM can only see files it created itself, not the rest of the Drive.
 */
class GoogleDriveDestination implements Destination
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    private const API = 'https://www.googleapis.com/drive/v3';

    private ?string $token = null;

    public function put(string $localPath, string $name): void
    {
        $size = filesize($localPath);
        $init = Http::withToken($this->token())->withHeaders(['X-Upload-Content-Type' => 'application/octet-stream', 'X-Upload-Content-Length' => $size])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', ['name' => $name, 'parents' => [$this->folderId()]])
            ->throw();
        $url = $init->header('Location');

        $fh = fopen($localPath, 'rb');
        $chunk = 8 * 1024 * 1024; // multiple of 256 KB, as Google requires
        $offset = 0;
        try {
            while ($offset < $size) {
                $data = fread($fh, $chunk);
                $end = $offset + strlen($data) - 1;
                $res = Http::withToken($this->token())->withBody($data, 'application/octet-stream')
                    ->withHeaders(['Content-Range' => "bytes $offset-$end/$size"])->timeout(120)->put($url);
                if (! in_array($res->status(), [200, 201, 308], true)) {
                    throw new RuntimeException('Google Drive upload failed with status '.$res->status());
                }
                $offset = $end + 1;
            }
        } finally {
            fclose($fh);
        }
    }

    public function list(): array
    {
        return array_column($this->files(), 'name');
    }

    public function get(string $name, string $localPath): void
    {
        $id = $this->idOf($name);
        Http::withToken($this->token())->sink($localPath)->timeout(300)->get(self::API."/files/$id", ['alt' => 'media'])->throw();
    }

    public function delete(string $name): void
    {
        Http::withToken($this->token())->delete(self::API.'/files/'.$this->idOf($name))->throw();
    }

    public function label(): string
    {
        return 'Google Drive';
    }

    private function files(): array
    {
        return Http::withToken($this->token())->get(self::API.'/files', [
            'q' => "'".$this->folderId()."' in parents and trashed = false",
            'fields' => 'files(id,name)', 'pageSize' => 1000,
        ])->throw()->json('files', []);
    }

    private function idOf(string $name): string
    {
        foreach ($this->files() as $f) {
            if ($f['name'] === $name) {
                return $f['id'];
            }
        }
        throw new RuntimeException("Backup $name not found on Google Drive.");
    }

    private function folderId(): string
    {
        if ($id = Setting::get('google_drive_folder_id')) {
            return $id;
        }
        $id = Http::withToken($this->token())->post(self::API.'/files', [
            'name' => config('backup.google.folder_name'),
            'mimeType' => 'application/vnd.google-apps.folder',
        ])->throw()->json('id');
        Setting::put('google_drive_folder_id', $id);

        return $id;
    }

    private function token(): string
    {
        if ($this->token) {
            return $this->token;
        }
        $refresh = Setting::getSecret('google_drive_refresh_token');
        if (! $refresh) {
            throw new RuntimeException('Google Drive is not connected yet. Super Admin: Settings → Backups → Connect Google Drive.');
        }

        return $this->token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('backup.google.client_id'),
            'client_secret' => config('backup.google.client_secret'),
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ])->throw()->json('access_token');
    }
}
