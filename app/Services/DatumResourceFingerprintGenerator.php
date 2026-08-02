<?php

namespace App\Services;

use App\Models\Datum;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatumResourceFingerprintGenerator
{
    /** @var array<int, string> */
    public const BLOCKING_TYPES = [
        'file_sha256',
        'doi',
        'canonical_url',
        'h_index_profile',
    ];

    /**
     * @param  array<string, mixed>  $material
     * @return array<int, array{type: string, value_hash: string}>
     */
    public function forMaterial(array $material, ?int $yearId = null): array
    {
        $identifiers = [];
        $materialType = data_get($material, 'type');
        $metadata = data_get($material, 'article', data_get($material, 'data', []));
        $metadata = is_array($metadata) ? $metadata : [];

        $fileHash = data_get($material, 'sha256');
        if ($materialType === 'file' && $this->isSha256($fileHash)) {
            $identifiers[] = $this->identifier('file_sha256', Str::lower((string) $fileHash));
        }

        $originalName = $this->normalizeText(data_get($material, 'original_name'));
        if ($materialType === 'file' && $originalName !== null) {
            $identifiers[] = $this->identifier('filename_signature', $originalName);
        }

        $url = data_get($material, 'link');
        $canonicalUrl = is_string($url) ? $this->canonicalUrl($url) : null;
        if ($materialType === 'url' && $canonicalUrl !== null) {
            $identifiers[] = $this->identifier('canonical_url', $canonicalUrl);
        }

        $doi = $this->normalizeDoi(data_get($metadata, 'doi'))
            ?? $this->normalizeDoi($canonicalUrl);
        if ($doi !== null) {
            $identifiers[] = $this->identifier('doi', $doi);
        }

        $title = $this->normalizeText(data_get($metadata, 'name'));
        $journal = $this->normalizeText(data_get($metadata, 'journal'));
        if ($title !== null && $journal !== null) {
            $identifiers[] = $this->identifier(
                'article_signature',
                implode('|', [$title, $journal, (string) ($yearId ?? '')]),
            );
        }

        if ($materialType === 'h_index') {
            $profileLinks = collect(data_get($material, 'profiles', []))
                ->filter(fn (mixed $profile): bool => is_array($profile))
                ->map(fn (array $profile): ?string => $this->canonicalUrl((string) ($profile['link'] ?? '')))
                ->filter()
                ->sort()
                ->values();

            if ($profileLinks->isNotEmpty()) {
                $identifiers[] = $this->identifier('h_index_profile', $profileLinks->implode('|'));
            }
        }

        return collect($identifiers)
            ->unique(fn (array $identifier): string => $identifier['type'].'|'.$identifier['value_hash'])
            ->values()
            ->all();
    }

    /** @return array<int, array{type: string, value_hash: string}> */
    public function forDatum(Datum $datum): array
    {
        $material = is_array($datum->material) ? $datum->material : [];

        if (data_get($material, 'type') === 'file' && ! $this->isSha256(data_get($material, 'sha256'))) {
            $path = $datum->storagePath();
            $disk = Storage::disk($datum->storageDisk());

            if ($path !== null && $disk->exists($path)) {
                $hash = hash_file('sha256', $disk->path($path));

                if (is_string($hash)) {
                    $material['sha256'] = $hash;
                }
            }
        }

        return $this->forMaterial($material, $datum->year_id);
    }

    public function isBlocking(string $type): bool
    {
        return in_array($type, self::BLOCKING_TYPES, true);
    }

    private function normalizeDoi(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $value = rawurldecode(Str::lower(trim($value)));
        $value = preg_replace('~^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)~u', '', $value) ?? $value;

        if (! preg_match('~(10\.\d{4,9}/\S+)~u', $value, $matches)) {
            return null;
        }

        return rtrim($matches[1], " \t\n\r\0\x0B.,;)");
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', Str::lower(trim($value)));
        $normalized = preg_replace('/\s+/u', ' ', (string) $normalized);
        $normalized = trim((string) $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function canonicalUrl(string $url): ?string
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = Str::lower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = Str::lower($parts['host']);
        $port = isset($parts['port']) && ! (($scheme === 'http' && $parts['port'] === 80)
            || ($scheme === 'https' && $parts['port'] === 443))
                ? ':'.$parts['port']
                : '';
        $path = $parts['path'] ?? '';
        $path = $path === '/' ? '' : rtrim($path, '/');
        $query = $this->canonicalQuery($parts['query'] ?? null);

        return $scheme.'://'.$host.$port.$path.($query !== '' ? '?'.$query : '');
    }

    private function canonicalQuery(?string $query): string
    {
        if ($query === null || $query === '') {
            return '';
        }

        $pairs = collect(explode('&', $query))
            ->filter()
            ->reject(function (string $pair): bool {
                $key = Str::lower(rawurldecode(Str::before($pair, '=')));

                return Str::startsWith($key, 'utm_')
                    || in_array($key, ['fbclid', 'gclid', 'mc_cid', 'mc_eid'], true);
            })
            ->sort()
            ->values();

        return $pairs->implode('&');
    }

    /** @return array{type: string, value_hash: string} */
    private function identifier(string $type, string $value): array
    {
        return [
            'type' => $type,
            'value_hash' => hash('sha256', $type.':'.$value),
        ];
    }

    private function isSha256(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/i', $value) === 1;
    }
}
