<?php

use App\Models\Item;
use App\Models\ItemImage;
use Cloudinary\Cloudinary;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('images:migrate-to-cloudinary {--dry-run : Preview without saving DB changes}', function () {
    $cloudName = (string) config('cloudinary.cloud.cloud_name');
    $apiKey = (string) config('cloudinary.cloud.api_key');
    $apiSecret = (string) config('cloudinary.cloud.api_secret');
    $folder = (string) config('cloudinary.upload.folder', 'swapship_items');

    if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
        $this->error('Cloudinary config missing. Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET.');
        return 1;
    }

    $dryRun = (bool) $this->option('dry-run');
    $cloudinary = new Cloudinary([
        'cloud' => [
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ],
        'url' => ['secure' => true],
    ]);

    $uploaded = 0;
    $skipped = 0;
    $failed = 0;

    $this->info($dryRun ? 'Dry run mode: no DB updates will be written.' : 'Migrating item images + bill files to Cloudinary...');

    ItemImage::query()
        ->where('url', 'like', '/storage/%')
        ->orderBy('id')
        ->chunkById(100, function ($images) use (&$uploaded, &$skipped, &$failed, $cloudinary, $folder, $dryRun) {
            foreach ($images as $image) {
                $relativePath = ltrim(str_replace('/storage/', '', (string) $image->url), '/');
                if ($relativePath === '' || !Storage::disk('public')->exists($relativePath)) {
                    $skipped++;
                    $this->warn("Skip image #{$image->id}: local file missing ({$relativePath}).");
                    continue;
                }

                try {
                    $upload = $cloudinary->uploadApi()->upload(Storage::disk('public')->path($relativePath), [
                        'folder' => trim($folder, '/').'/items',
                        'resource_type' => 'image',
                    ]);
                    $secureUrl = (string) ($upload['secure_url'] ?? '');
                    if ($secureUrl === '') {
                        $failed++;
                        $this->error("Failed image #{$image->id}: Cloudinary returned empty URL.");
                        continue;
                    }

                    if (!$dryRun) {
                        $image->url = $secureUrl;
                        $image->save();
                    }
                    $uploaded++;
                    $this->line("Migrated image #{$image->id}");
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("Failed image #{$image->id}: {$e->getMessage()}");
                }
            }
        });

    Item::query()
        ->whereNotNull('bill_url')
        ->where('bill_url', 'like', '/storage/%')
        ->orderBy('id')
        ->chunkById(100, function ($items) use (&$uploaded, &$skipped, &$failed, $cloudinary, $folder, $dryRun) {
            foreach ($items as $item) {
                $relativePath = ltrim(str_replace('/storage/', '', (string) $item->bill_url), '/');
                if ($relativePath === '' || !Storage::disk('public')->exists($relativePath)) {
                    $skipped++;
                    $this->warn("Skip bill item #{$item->id}: local file missing ({$relativePath}).");
                    continue;
                }

                try {
                    $mime = Storage::disk('public')->mimeType($relativePath) ?: '';
                    $resourceType = str_starts_with($mime, 'image/') ? 'image' : 'raw';
                    $upload = $cloudinary->uploadApi()->upload(Storage::disk('public')->path($relativePath), [
                        'folder' => trim($folder, '/').'/bills',
                        'resource_type' => $resourceType,
                    ]);
                    $secureUrl = (string) ($upload['secure_url'] ?? '');
                    if ($secureUrl === '') {
                        $failed++;
                        $this->error("Failed bill item #{$item->id}: Cloudinary returned empty URL.");
                        continue;
                    }

                    if (!$dryRun) {
                        $item->bill_url = $secureUrl;
                        $item->save();
                    }
                    $uploaded++;
                    $this->line("Migrated bill for item #{$item->id}");
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("Failed bill item #{$item->id}: {$e->getMessage()}");
                }
            }
        });

    $this->newLine();
    $this->info("Done. Uploaded: {$uploaded}, Skipped: {$skipped}, Failed: {$failed}");
    return $failed > 0 ? 1 : 0;
})->purpose('Migrate old /storage item files to Cloudinary');
