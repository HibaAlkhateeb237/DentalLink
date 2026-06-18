<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class GenerateDentalScanImages extends Command
{
    protected $signature = 'dental:generate-scan-images {--force : Overwrite existing files}';

    protected $description = 'Generate realistic dental impression/scan images for seed data';

    private const WIDTH = 800;

    private const HEIGHT = 600;

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $baseDir = 'seed-files';

        if (! $disk->exists($baseDir)) {
            $disk->makeDirectory($baseDir);
        }

        $required = [
            'before-scan-1.jpg',
            'before-scan-2.jpg',
            'before-scan-3.jpg',
            'after-scan-1.jpg',
            'after-scan-2.jpg',
            'after-scan-3.jpg',
        ];

        $existing = collect($required)->filter(fn (string $name): bool => $disk->exists("{$baseDir}/{$name}"));

        if ($existing->isNotEmpty() && ! $this->option('force')) {
            $this->warn('Some scan images already exist. Use --force to regenerate.');
        }

        $this->generateBeforeScan($disk, $baseDir, 'before-scan-1.jpg', 'upper', false);
        $this->generateBeforeScan($disk, $baseDir, 'before-scan-2.jpg', 'lower', false);
        $this->generateBeforeScan($disk, $baseDir, 'before-scan-3.jpg', 'upper', true);
        $this->generateAfterScan($disk, $baseDir, 'after-scan-1.jpg', 'upper', false);
        $this->generateAfterScan($disk, $baseDir, 'after-scan-2.jpg', 'lower', false);
        $this->generateAfterScan($disk, $baseDir, 'after-scan-3.jpg', 'upper', true);

        $this->info('Generated 6 dental scan images in storage/app/public/seed-files/');

        return 0;
    }

    private function createViewport(): \GdImage
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        // Dark teal scanner viewport background
        $bg = imagecolorallocate($img, 18, 30, 38);
        imagefill($img, 0, 0, $bg);

        // Subtle grid overlay
        $gridColor = imagecolorallocatealpha($img, 50, 80, 100, 80);
        for ($x = 0; $x < self::WIDTH; $x += 40) {
            imageline($img, $x, 0, $x, self::HEIGHT, $gridColor);
        }
        for ($y = 0; $y < self::HEIGHT; $y += 40) {
            imageline($img, 0, $y, self::WIDTH, $y, $gridColor);
        }

        return $img;
    }

    /**
     * @param  array<int, array{float, float}>  $points
     */
    private function drawMeshOverlay(\GdImage $img, array $points, int $color): void
    {
        $count = count($points);
        for ($i = 0; $i < $count; $i++) {
            [$x1, $y1] = $points[$i];
            for ($j = $i + 1; $j < $count; $j++) {
                [$x2, $y2] = $points[$j];
                $dist = sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
                if ($dist < 60) {
                    imageline($img, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $color);
                }
            }
        }
    }

    private function generateBeforeScan(FilesystemAdapter $disk, string $baseDir, string $filename, string $arch, bool $withDiastema): void
    {
        $img = $this->createViewport();
        $cx = self::WIDTH / 2;
        $cy = self::HEIGHT / 2 + 20;

        // Gum tissue base
        $gumColor = imagecolorallocate($img, 200, 140, 130);
        $gumDark = imagecolorallocate($img, 170, 110, 100);

        // Dental arch - draw as filled curve
        $archTop = $arch === 'upper' ? $cy - 100 : $cy - 60;
        $archBottom = $arch === 'upper' ? $cy + 60 : $cy + 100;
        $archLeft = $cx - 200;
        $archRight = $cx + 200;

        // Gum area
        imagefilledellipse($img, $cx, $cy, 420, 220, $gumColor);
        imagefilledellipse($img, $cx, $cy, 340, 160, $gumDark);

        // Teeth positions (U-shaped arch)
        $teethPositions = $this->getToothPositions($cx, $cy, $arch);

        // Tooth color
        $toothColor = imagecolorallocate($img, 240, 235, 220);
        $toothOutline = imagecolorallocate($img, 200, 190, 170);
        $scanHighlight = imagecolorallocatealpha($img, 150, 200, 255, 60);

        // Draw each tooth
        foreach ($teethPositions as $i => $pos) {
            $tx = $pos[0];
            $ty = $pos[1];

            // Skip if diastema and this is a central
            if ($withDiastema && ($i === 3 || $i === 4)) {
                continue;
            }

            // Tooth shape (rounded rectangle)
            $tw = 30;
            $th = 38;
            imagefilledellipse($img, $tx, $ty, $tw, $th, $toothColor);
            imageellipse($img, $tx, $ty, $tw, $th, $toothOutline);

            // Highlight on tooth
            imagefilledellipse($img, $tx - 4, $ty - 5, 10, 14, $scanHighlight);
        }

        // Scan mesh overlay points
        $meshPoints = [];
        for ($i = 0; $i < 30; $i++) {
            $angle = pi() * 0.8 + (pi() * 0.4 * ($i / 29));
            $radius = 140 + (($i % 3) * 20);
            $mx = $cx + cos($angle) * $radius;
            $my = $cy - 30 + sin($angle) * $radius * 0.6;
            $meshPoints[] = [$mx, $my];
        }

        $meshColor = imagecolorallocatealpha($img, 100, 200, 255, 40);
        $this->drawMeshOverlay($img, $meshPoints, $meshColor);

        // Scanner info overlay
        $textColor = imagecolorallocatealpha($img, 100, 200, 255, 60);
        $fontPath = null;

        if ($arch === 'upper') {
            $label = 'Maxillary Arch - Intraoral Scan';
        } else {
            $label = 'Mandibular Arch - Intraoral Scan';
        }

        // Simple text rendering (GD's built-in font)
        for ($i = 0; $i < strlen($label); $i++) {
            $charX = 30 + $i * 12;
            $charY = self::HEIGHT - 40;
            imagechar($img, 2, $charX, $charY, $label[$i], $textColor);
        }

        $statusLabel = 'Status: Complete  |  Teeth: 14/14  |  Quality: High';
        for ($i = 0; $i < strlen($statusLabel); $i++) {
            $charX = 30 + $i * 12;
            $charY = self::HEIGHT - 20;
            imagechar($img, 1, $charX, $charY, $statusLabel[$i], $textColor);
        }

        $this->saveImage($img, $disk, $baseDir, $filename);
    }

    private function generateAfterScan(FilesystemAdapter $disk, string $baseDir, string $filename, string $arch, bool $withPrep): void
    {
        $img = $this->createViewport();
        $cx = self::WIDTH / 2;
        $cy = self::HEIGHT / 2 + 20;

        // Gum tissue (slightly redder - post preparation)
        $gumColor = imagecolorallocate($img, 210, 130, 120);
        $gumDark = imagecolorallocate($img, 180, 100, 90);
        $prepColor = imagecolorallocate($img, 230, 220, 200);
        $prepOutline = imagecolorallocate($img, 190, 180, 160);
        $marginColor = imagecolorallocate($img, 220, 80, 60);

        // Gum area
        imagefilledellipse($img, $cx, $cy, 420, 220, $gumColor);
        imagefilledellipse($img, $cx, $cy, 340, 160, $gumDark);

        $teethPositions = $this->getToothPositions($cx, $cy, $arch);

        // Which teeth to prepare
        $prepIndices = $withPrep ? [3, 4, 5, 6] : [5, 6];

        foreach ($teethPositions as $i => $pos) {
            $tx = $pos[0];
            $ty = $pos[1];

            $isPrepared = in_array($i, $prepIndices, true);

            if ($isPrepared) {
                // Prepared tooth (stump) - smaller, with margin line
                $tw = 22;
                $th = 28;
                imagefilledellipse($img, $tx, $ty, $tw, $th, $prepColor);
                imageellipse($img, $tx, $ty, $tw, $th, $prepOutline);

                // Margin line (red outline at base)
                imageellipse($img, $tx, $ty + 10, $tw + 4, 12, $marginColor);

                // Inner dentin color
                $dentinColor = imagecolorallocate($img, 210, 190, 160);
                imagefilledellipse($img, $tx, $ty, 12, 16, $dentinColor);

                // Scan highlight
                $scanHighlight = imagecolorallocatealpha($img, 150, 200, 255, 60);
                imagefilledellipse($img, $tx - 3, $ty - 3, 8, 10, $scanHighlight);
            } else {
                // Normal unprepared tooth
                $toothColor = imagecolorallocate($img, 240, 235, 220);
                $toothOutline = imagecolorallocate($img, 200, 190, 170);
                $tw = 30;
                $th = 38;
                imagefilledellipse($img, $tx, $ty, $tw, $th, $toothColor);
                imageellipse($img, $tx, $ty, $tw, $th, $toothOutline);

                $scanHighlight = imagecolorallocatealpha($img, 150, 200, 255, 60);
                imagefilledellipse($img, $tx - 4, $ty - 5, 10, 14, $scanHighlight);
            }
        }

        // Scan mesh
        $meshPoints = [];
        for ($i = 0; $i < 30; $i++) {
            $angle = pi() * 0.8 + (pi() * 0.4 * ($i / 29));
            $radius = 140 + (($i % 3) * 20);
            $mx = $cx + cos($angle) * $radius;
            $my = $cy - 30 + sin($angle) * $radius * 0.6;
            $meshPoints[] = [$mx, $my];
        }

        $meshColor = imagecolorallocatealpha($img, 100, 200, 255, 40);
        $this->drawMeshOverlay($img, $meshPoints, $meshColor);

        // Info overlay
        $textColor = imagecolorallocatealpha($img, 100, 200, 255, 60);
        $archLabel = $arch === 'upper' ? 'Maxillary' : 'Mandibular';
        $label = "{$archLabel} Arch - Post Preparation Scan";
        for ($i = 0; $i < strlen($label); $i++) {
            $charX = 30 + $i * 12;
            $charY = self::HEIGHT - 40;
            imagechar($img, 2, $charX, $charY, $label[$i], $textColor);
        }

        $prepCount = count($prepIndices);
        $statusLabel = "Status: Complete  |  Prepared: {$prepCount} teeth  |  Margin: Visible";
        for ($i = 0; $i < strlen($statusLabel); $i++) {
            $charX = 30 + $i * 12;
            $charY = self::HEIGHT - 20;
            imagechar($img, 1, $charX, $charY, $statusLabel[$i], $textColor);
        }

        $this->saveImage($img, $disk, $baseDir, $filename);
    }

    /**
     * @return array<int, array{int, int}>
     */
    private function getToothPositions(int $cx, int $cy, string $arch): array
    {
        // Returns 8 tooth positions for one half of the arch (mirrored)
        $positions = [];
        $archFactor = $arch === 'upper' ? 1 : 0.85;

        $angles = [1.5, 1.2, 0.9, 0.6, 0.3, 0.0, -0.3, -0.6];
        $radiusX = [60, 100, 140, 170, 185, 190, 185, 170];
        $radiusY = [30, 55, 80, 100, 110, 115, 110, 100];

        foreach ($angles as $i => $angle) {
            $rx = $radiusX[$i];
            $ry = (int) ($radiusY[$i] * $archFactor);

            // Right side
            $x1 = $cx + (int) (cos($angle) * $rx);
            $y1 = $cy + (int) ($arch === 'upper' ? -sin(abs($angle)) * $ry : sin(abs($angle)) * $ry) - 30;
            $positions[] = [$x1, $y1];

            // Left side (mirror)
            if ($angle !== 0.0 || $i === 0) {
                $x2 = $cx - (int) (cos($angle) * $rx);
                $y2 = $cy + (int) ($arch === 'upper' ? -sin(abs($angle)) * $ry : sin(abs($angle)) * $ry) - 30;

                if ($i !== 0 || $angle !== 0.0) {
                    $positions[] = [$x2, $y2];
                }
            }
        }

        return $positions;
    }

    private function saveImage(\GdImage $img, FilesystemAdapter $disk, string $baseDir, string $filename): void
    {
        $path = "{$baseDir}/{$filename}";
        ob_start();
        imagejpeg($img, null, 85);
        $contents = ob_get_clean();
        $disk->put($path, $contents);
        imagedestroy($img);
    }
}
