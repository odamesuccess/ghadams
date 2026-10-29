<?php

namespace App\Services;

use Exception;
use QRcode;

class QrCodeGenerator
{
    public static function generateWithLogo(
        string $data,
        string $filename,
        ?string $logoPath = null
    ): void {
        $tempQR = tempnam(sys_get_temp_dir(), 'qr_');
        QRcode::png($data, $tempQR, 'H', 20, 2);

        if (! file_exists($tempQR)) {
            throw new Exception('QR code generation failed.');
        }

        $qrImage = imagecreatefrompng($tempQR);
        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);

        $borderThickness = 20;
        $totalWidth = $qrWidth + (2 * $borderThickness);
        $totalHeight = $qrHeight + (2 * $borderThickness);

        $qrWithBorder = imagecreatetruecolor($totalWidth, $totalHeight);
        $borderColor = imagecolorallocate($qrWithBorder, 0, 0, 0);
        imagefill($qrWithBorder, 0, 0, $borderColor);

        imagecopy(
            $qrWithBorder,
            $qrImage,
            $borderThickness,
            $borderThickness,
            0,
            0,
            $qrWidth,
            $qrHeight
        );

        // Optional logo
        if ($logoPath && file_exists($logoPath)) {
            self::addLogo($qrWithBorder, $logoPath, $qrWidth, $qrHeight, $borderThickness);
        }

        imagepng($qrWithBorder, $filename, 0);

        imagedestroy($qrImage);
        imagedestroy($qrWithBorder);
        unlink($tempQR);
    }

    private static function addLogo($qrWithBorder, $logoPath, $qrWidth, $qrHeight, $border)
    {
        $logoExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $logo = match ($logoExt) {
            'png' => imagecreatefrompng($logoPath),
            'jpg', 'jpeg' => imagecreatefromjpeg($logoPath),
            default => throw new Exception('Unsupported logo format'),
        };

        $logoTarget = (int) ($qrWidth * 0.2);
        $logoX = ($qrWidth + 2 * $border - $logoTarget) / 2;
        $logoY = ($qrHeight + 2 * $border - $logoTarget) / 2;

        $resized = imagecreatetruecolor($logoTarget, $logoTarget);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled(
            $resized,
            $logo,
            0,
            0,
            0,
            0,
            $logoTarget,
            $logoTarget,
            imagesx($logo),
            imagesy($logo)
        );

        imagecopy($qrWithBorder, $resized, $logoX, $logoY, 0, 0, $logoTarget, $logoTarget);

        imagedestroy($logo);
        imagedestroy($resized);
    }
}
