<?php

namespace App\Support;

/**
 * Apply the GhanaDAMS institutional watermark using the same DomPDF canvas
 * page_script approach already proven to work correctly on the Inventory
 * by Office report.
 *
 * IMPORTANT:
 * This helper must be called AFTER Pdf::loadView()/setPaper() and BEFORE
 * stream()/download(). That timing is intentional. It registers the page
 * script before DomPDF performs the final render, so the report text remains
 * clear while the faded institutional image is visible across the page.
 */
class PdfWatermark
{
    public static function apply(
        $pdf,
        bool $showPageNumbers = false,
        int $pageNumberRightOffset = 140
    ): void {
        $watermarkImage = storage_path(
            'app/public/water_mark/watermark_image2.png'
        );

        $canvas = $pdf->getDomPDF()->getCanvas();

        $canvas->page_script(
            function (
                $pageNumber,
                $pageCount,
                $canvas
            ) use (
                $watermarkImage,
                $showPageNumbers,
                $pageNumberRightOffset
            ): void {
                $width = $canvas->get_width();
                $height = $canvas->get_height();

                if (is_file($watermarkImage)) {
                    /*
                     * Match the already-working Inventory by Office method.
                     * The PNG itself is already faded, so the report remains
                     * readable when the page script is registered BEFORE the
                     * final DomPDF render.
                     */
                    $canvas->set_opacity(0.99);

                    $canvas->image(
                        $watermarkImage,
                        0,
                        0,
                        $width,
                        $height
                    );
                }

                $canvas->set_opacity(1);

                if ($showPageNumbers) {
                    $canvas->text(
                        $width - $pageNumberRightOffset,
                        $height - 25,
                        "Page {$pageNumber} of {$pageCount}",
                        null,
                        9
                    );
                }
            }
        );
    }

    /**
     * Apply one professionally centred Coat of Arms watermark.
     *
     * Existing reports continue using apply(); this method is intended for
     * formal portrait documents such as the Maintenance Job PV support PDF.
     */
    public static function applyCentered(
        $pdf,
        bool $showPageNumbers = false,
        int $pageNumberRightOffset = 140,
        float $widthRatio = 0.58
    ): void {
        $watermarkImage = storage_path(
            'app/public/water_mark/watermark_image2.png'
        );

        $canvas = $pdf->getDomPDF()->getCanvas();

        $imageRatio = 0.75;

        if (is_file($watermarkImage)) {
            $imageSize = @getimagesize($watermarkImage);

            if (is_array($imageSize) && ! empty($imageSize[0])) {
                $imageRatio = $imageSize[1] / $imageSize[0];
            }
        }

        $canvas->page_script(
            function (
                $pageNumber,
                $pageCount,
                $canvas
            ) use (
                $watermarkImage,
                $showPageNumbers,
                $pageNumberRightOffset,
                $widthRatio,
                $imageRatio
            ): void {
                $width = $canvas->get_width();
                $height = $canvas->get_height();

                if (is_file($watermarkImage)) {
                    $watermarkWidth = $width * $widthRatio;
                    $watermarkHeight = $watermarkWidth * $imageRatio;

                    $x = ($width - $watermarkWidth) / 2;
                    $y = ($height - $watermarkHeight) / 2;

                    $canvas->set_opacity(0.99);
                    $canvas->image(
                        $watermarkImage,
                        $x,
                        $y,
                        $watermarkWidth,
                        $watermarkHeight
                    );
                }

                $canvas->set_opacity(1);

                if ($showPageNumbers) {
                    $canvas->text(
                        $width - $pageNumberRightOffset,
                        $height - 25,
                        "Page {$pageNumber} of {$pageCount}",
                        null,
                        9
                    );
                }
            }
        );
    }

}
