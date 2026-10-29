<?php

namespace App\Services;

use App\Models\Asset;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Margin\Margin;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class DemoAssetQrService
{
    public function generateForAsset(Asset $asset): string
    {
        $dir = 'assets/qrcodes';
        $path = "{$dir}/asset-{$asset->id}.png";

        Storage::disk('public')->makeDirectory($dir);

        $qrCode = new QrCode(
            data: route('assets.scan', $asset->uuid),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 900,
            margin: 12,
        );

        $label = new Label(
            text: $asset->asset_code,
            margin: new Margin(6, 0, 12, 0),
            font: new OpenSans(40),
        );

        $writer = new PngWriter();
        $logo = $this->logo();

        $result = $logo
            ? $writer->write($qrCode, $logo, $label)
            : $writer->write($qrCode, null, $label);

        Storage::disk('public')->put($path, $result->getString());

        $asset->updateQuietly(['qr_code' => $path]);

        return $path;
    }

    private function logo(): ?Logo
    {
        $paths = [
            public_path('images/ghadams_logo.jpg'),
            public_path('images/ghadams_logo.png'),
            public_path('applogo.jpg'),
            public_path('applogo.png'),
            storage_path('app/public/logo/ghadams_logo.jpg'),
            storage_path('app/public/logo/ghadams_logo.png'),
        ];

        foreach ($paths as $path) {
            if (is_file($path) && @getimagesize($path) !== false) {
                return new Logo(path: $path, resizeToWidth: 150);
            }
        }

        return null;
    }
}
