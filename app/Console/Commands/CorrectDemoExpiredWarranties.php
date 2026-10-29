<?php
namespace App\Console\Commands;
use App\Models\Asset;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Margin\Margin;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;
class CorrectDemoExpiredWarranties extends Command
{
    protected $signature = 'assets:correct-demo-warranties';
    protected $description = 'Keep 20 expired active warranties; correct demo dates and rebuild codes and QR labels';
    public function handle(): int
    {
        @set_time_limit(0);
        $published = [];
        $disk = Storage::disk('public');
        $backup = dirname(base_path()) . '/GhanaDAMS_BACKUPS/warranty20-' . now()->format('Ymd-His') . '-' . Str::random(5);
        try {
            File::ensureDirectoryExists($backup);
            DB::transaction(function () use ($disk, $backup, &$published): void {
                $all = Asset::withTrashed()->lockForUpdate()->get();
                $today = Carbon::today();
                $expired = $all->filter(fn ($a) => !$a->deleted_at && $a->status === 'active' && $a->warranty_end_date && $a->warranty_end_date->lt($today));
                $demo = $expired->filter(fn ($a) => in_array($a->description, [
                    'Bulk generated asset for dashboard stress testing',
                    'GhanaDAMS realistic demonstration data - synthetic record; not a verified physical GES asset.',
                ], true))->sortBy(fn ($a) => $a->purchase_date->format('Y-m-d') . sprintf('%012d', $a->id))->values();
                $protected = $expired->count() - $demo->count();
                if ($protected > 20 || $expired->count() < 20) {
                    throw new RuntimeException('Cannot reach 20 by reducing demo records only. Expired: ' . $expired->count() . '; protected non-demo: ' . $protected);
                }
                $change = $demo->slice(20 - $protected);
                $this->line('Expired before: ' . $expired->count() . '; demo purchase dates to correct: ' . $change->count());
                $json = json_encode($all->map(fn ($a) => $a->getAttributes())->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (File::put($backup . '/assets-before.json', $json) === false || File::get($backup . '/assets-before.json') !== $json) {
                    throw new RuntimeException('Record backup failed.');
                }
                foreach ($change as $a) {
                    $duration = (int) $a->warranty_duration;
                    if ($duration < 1) { throw new RuntimeException('Invalid warranty duration on asset ' . $a->id); }
                    // Stay within the existing warranty term and no more than 90 days before today.
                    $maxDays = max(0, min(90, (int) $today->copy()->subMonthsNoOverflow($duration)->diffInDays($today, true) - 2));
                    $purchase = $today->copy()->subDays($a->id % ($maxDays + 1));
                    $end = $purchase->copy()->addMonthsNoOverflow($duration);
                    $a->updateQuietly(['purchase_date' => $purchase->toDateString(), 'warranty_end_date' => $end->toDateString()]);
                }
                if ($this->call('assets:rebuild-warranty-codes', ['--apply' => true]) !== 0) {
                    throw new RuntimeException('Code rebuild failed; dates and codes rolled back.');
                }
                $live = Asset::orderBy('id')->get();
                File::ensureDirectoryExists($backup . '/new-qr');
                File::ensureDirectoryExists($backup . '/old-qr');
                foreach ($live as $a) {
                    if (!$a->uuid) { throw new RuntimeException('Missing scan UUID for asset ' . $a->id); }
                    $path = 'assets/qrcodes/asset-' . $a->id . '.png';
                    $old = $disk->exists($path);
                    if ($old && (!File::copy($disk->path($path), $backup . '/old-qr/asset-' . $a->id . '.png') || hash_file('sha256', $disk->path($path)) !== hash_file('sha256', $backup . '/old-qr/asset-' . $a->id . '.png'))) {
                        throw new RuntimeException('QR backup failed.');
                    }
                    $qr = new QrCode(data: route('assets.scan', $a->uuid), encoding: new Encoding('UTF-8'), errorCorrectionLevel: ErrorCorrectionLevel::High, size: 900, margin: 12);
                    $label = new Label(text: $a->asset_code, margin: new Margin(6, 0, 12, 0), font: new OpenSans(40));
                    $bytes = (new PngWriter())->write($qr, null, $label)->getString();
                    if (@getimagesizefromstring($bytes) === false || File::put($backup . '/new-qr/asset-' . $a->id . '.png', $bytes) === false) { throw new RuntimeException('QR staging failed.'); }
                    $a->updateQuietly(['qr_code' => $path]);
                }
                if (Asset::where('status', 'active')->whereDate('warranty_end_date', '<', $today->toDateString())->count() !== 20) {
                    throw new RuntimeException('Final warranty count failed; changes rolled back.');
                }
                $disk->makeDirectory('assets/qrcodes');
                foreach ($live as $a) {
                    $path = 'assets/qrcodes/asset-' . $a->id . '.png';
                    $published[] = ['path' => $path, 'backup' => $backup . '/old-qr/asset-' . $a->id . '.png'];
                    if (!$disk->put($path, File::get($backup . '/new-qr/asset-' . $a->id . '.png'))) { throw new RuntimeException('QR publication failed.'); }
                }
                $this->info('Expired active warranties: 20. Codes and QR labels rebuilt for ' . $live->count() . ' assets.');
            });
            $this->line('Backup: ' . $backup);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            foreach ($published as $file) {
                if (is_file($file['backup'])) { $disk->put($file['path'], File::get($file['backup'])); }
                else { $disk->delete($file['path']); }
            }
            $this->error($e->getMessage());
            $this->line('Backup: ' . $backup);
            return self::FAILURE;
        }
    }
}
