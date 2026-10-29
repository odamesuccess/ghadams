<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeController extends Controller
{
    public function qr()
    {
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return response(
            $writer->writeString('ASSET-001'),
            200,
            ['Content-Type' => 'image/svg+xml']
        );
    }
}
