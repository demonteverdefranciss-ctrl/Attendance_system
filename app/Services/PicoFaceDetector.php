<?php

namespace App\Services;

/**
 * MIT pico.js cascade runner (nenadmarkus/pico).
 * Detects frontal faces in a still image using GD; no Python required.
 */
class PicoFaceDetector
{
    private const MAX_SIDE = 256;

    /** @var callable|null */
    private static $classify = null;

    /**
     * @return array<int, array{x: int, y: int, size: int, score: float}>
     */
    public function detect(string $path): array
    {
        $image = $this->loadImage($path);
        if ($image === null) {
            return [];
        }

        $origW = imagesx($image);
        $origH = imagesy($image);
        if ($origW < 1 || $origH < 1) {
            imagedestroy($image);

            return [];
        }

        try {
            $image = $this->applyJpegOrientation($image, $path);
        } catch (\Throwable) {
            // Keep the decoded pixels; a bad EXIF block must not abort detection.
        }

        $origW = imagesx($image);
        $origH = imagesy($image);
        $scale = max($origW, $origH) / self::MAX_SIDE;
        if ($scale < 1) {
            $scale = 1;
        }
        $workW = max(1, (int) round($origW / $scale));
        $workH = max(1, (int) round($origH / $scale));

        $work = $image;
        if ($workW !== $origW || $workH !== $origH) {
            $work = imagecreatetruecolor($workW, $workH);
            if ($work === false) {
                imagedestroy($image);

                throw new \RuntimeException('Could not resize the photo for face detection.');
            }
            imagecopyresampled($work, $image, 0, 0, 0, 0, $workW, $workH, $origW, $origH);
            imagedestroy($image);
        }

        $pixels = $this->grayscale($work, $workW, $workH);
        imagedestroy($work);

        $classify = $this->classifier();
        $dets = $this->runCascade($pixels, $workH, $workW, $classify);
        $clusters = $this->clusterDetections($dets, 0.2);

        $faces = [];
        foreach ($clusters as $cluster) {
            if ($cluster[3] < 5.0) {
                continue;
            }
            $size = (int) round($cluster[2] * $scale);
            $faces[] = [
                'x' => (int) round(($cluster[1] - $cluster[2] / 2) * $scale),
                'y' => (int) round(($cluster[0] - $cluster[2] / 2) * $scale),
                'size' => max(1, $size),
                'score' => (float) $cluster[3],
            ];
        }

        return $faces;
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function orientedDimensions(string $path): array
    {
        $info = @getimagesize($path);
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $orientation = $this->jpegOrientation($path);
        if (in_array($orientation, [5, 6, 7, 8], true)) {
            return [$height, $width];
        }

        return [$width, $height];
    }

    /**
     * @return \GdImage|resource|null
     */
    private function loadImage(string $path)
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        return match ($info[2] ?? 0) {
            IMAGETYPE_JPEG => $this->loadJpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }

    /**
     * @return \GdImage|resource|null
     */
    private function loadJpeg(string $path)
    {
        $image = @imagecreatefromjpeg($path);
        if ($image) {
            return $image;
        }

        $bytes = @file_get_contents($path);

        return $bytes !== false ? (@imagecreatefromstring($bytes) ?: null) : null;
    }

    /**
     * Phone portraits are often stored sideways with EXIF orientation.
     * GD does not apply that tag, so a real face looks rotated and is missed.
     *
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function applyJpegOrientation($image, string $path)
    {
        $orientation = $this->jpegOrientation($path);
        if (! function_exists('imagerotate') || $orientation === 1) {
            return $image;
        }

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated === false || $rotated === null) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function jpegOrientation(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        try {
            $exif = @exif_read_data($path);
        } catch (\Throwable) {
            return 1;
        }

        $orientation = (int) ($exif['Orientation'] ?? 1);

        return ($orientation >= 1 && $orientation <= 8) ? $orientation : 1;
    }

    /**
     * @param  \GdImage|resource  $image
     * @return array<int, int>
     */
    private function grayscale($image, int $width, int $height): array
    {
        $pixels = [];
        for ($r = 0; $r < $height; $r++) {
            for ($c = 0; $c < $width; $c++) {
                $rgb = imagecolorat($image, $c, $r);
                $red = ($rgb >> 16) & 0xFF;
                $green = ($rgb >> 8) & 0xFF;
                $blue = $rgb & 0xFF;
                $pixels[$r * $width + $c] = (int) ((2 * $red + 7 * $green + $blue) / 10);
            }
        }

        return $pixels;
    }

    private function classifier(): callable
    {
        if (self::$classify !== null) {
            return self::$classify;
        }

        $bytes = false;
        foreach ([
            resource_path('face-detection/facefinder'),
            public_path('face-detection/facefinder'),
            base_path('resources/face-detection/facefinder'),
        ] as $path) {
            if (is_file($path)) {
                $bytes = @file_get_contents($path);
                if ($bytes !== false && $bytes !== '') {
                    break;
                }
            }
        }
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('Face-detection cascade is missing.');
        }

        self::$classify = $this->unpackCascade($bytes);

        return self::$classify;
    }

    private function unpackCascade(string $bytes): callable
    {
        $p = 8;
        $tdepth = $this->i32le($bytes, $p);
        $p += 4;
        $ntrees = $this->i32le($bytes, $p);
        $p += 4;
        $pow2 = 1 << $tdepth;

        $tcodes = [];
        $tpreds = [];
        $thresh = [];

        for ($t = 0; $t < $ntrees; $t++) {
            $tcodes[] = 0;
            $tcodes[] = 0;
            $tcodes[] = 0;
            $tcodes[] = 0;
            $codeLen = 4 * $pow2 - 4;
            for ($i = 0; $i < $codeLen; $i++) {
                $tcodes[] = $this->i8($bytes, $p + $i);
            }
            $p += $codeLen;
            for ($i = 0; $i < $pow2; $i++) {
                $tpreds[] = $this->f32le($bytes, $p);
                $p += 4;
            }
            $thresh[] = $this->f32le($bytes, $p);
            $p += 4;
        }

        return function (int $r, int $c, int $s, array $pixels, int $ldim) use ($tdepth, $ntrees, $pow2, $tcodes, $tpreds, $thresh): float {
            $r *= 256;
            $c *= 256;
            $root = 0;
            $o = 0.0;

            for ($i = 0; $i < $ntrees; $i++) {
                $idx = 1;
                for ($j = 0; $j < $tdepth; $j++) {
                    $base = $root + 4 * $idx;
                    $left = $pixels[(($r + $tcodes[$base] * $s) >> 8) * $ldim + (($c + $tcodes[$base + 1] * $s) >> 8)]
                        ?? 0;
                    $right = $pixels[(($r + $tcodes[$base + 2] * $s) >> 8) * $ldim + (($c + $tcodes[$base + 3] * $s) >> 8)]
                        ?? 0;
                    $idx = 2 * $idx + ($left <= $right ? 1 : 0);
                }
                $o += $tpreds[$pow2 * $i + $idx - $pow2];
                if ($o <= $thresh[$i]) {
                    return -1.0;
                }
                $root += 4 * $pow2;
            }

            return $o - $thresh[$ntrees - 1];
        };
    }

    /**
     * @param  array<int, int>  $pixels
     * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
     */
    private function runCascade(array $pixels, int $nrows, int $ncols, callable $classify): array
    {
        $shiftfactor = 0.12;
        $scalefactor = 1.2;
        $minsize = max(24, (int) round(min($nrows, $ncols) * 0.08));
        $maxsize = min($nrows, $ncols);
        $detections = [];
        $scale = (float) $minsize;

        while ($scale <= $maxsize) {
            $step = max((int) ($shiftfactor * $scale), 1);
            $offset = (int) ($scale / 2 + 1);
            $s = (int) $scale;
            for ($r = $offset; $r <= $nrows - $offset; $r += $step) {
                for ($c = $offset; $c <= $ncols - $offset; $c += $step) {
                    $q = $classify($r, $c, $s, $pixels, $ncols);
                    if ($q > 0.0) {
                        $detections[] = [(float) $r, (float) $c, (float) $s, (float) $q];
                    }
                }
            }
            $scale *= $scalefactor;
        }

        return $detections;
    }

    /**
     * @param  array<int, array{0: float, 1: float, 2: float, 3: float}>  $dets
     * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
     */
    private function clusterDetections(array $dets, float $iouThreshold): array
    {
        usort($dets, fn (array $a, array $b) => $b[3] <=> $a[3]);
        $assigned = array_fill(0, count($dets), false);
        $clusters = [];

        foreach ($dets as $i => $det) {
            if ($assigned[$i]) {
                continue;
            }
            $r = 0.0;
            $c = 0.0;
            $s = 0.0;
            $q = 0.0;
            $n = 0;
            foreach ($dets as $j => $other) {
                if ($this->iou($det, $other) <= $iouThreshold) {
                    continue;
                }
                $assigned[$j] = true;
                $r += $other[0];
                $c += $other[1];
                $s += $other[2];
                $q += $other[3];
                $n++;
            }
            if ($n > 0) {
                $clusters[] = [$r / $n, $c / $n, $s / $n, $q];
            }
        }

        return $clusters;
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $a
     * @param  array{0: float, 1: float, 2: float, 3: float}  $b
     */
    private function iou(array $a, array $b): float
    {
        $overr = max(0.0, min($a[0] + $a[2] / 2, $b[0] + $b[2] / 2) - max($a[0] - $a[2] / 2, $b[0] - $b[2] / 2));
        $overc = max(0.0, min($a[1] + $a[2] / 2, $b[1] + $b[2] / 2) - max($a[1] - $a[2] / 2, $b[1] - $b[2] / 2));
        $den = $a[2] * $a[2] + $b[2] * $b[2] - $overr * $overc;

        return $den <= 0 ? 0.0 : ($overr * $overc) / $den;
    }

    private function i32le(string $bin, int $off): int
    {
        $u = unpack('V', substr($bin, $off, 4))[1];

        return $u >= 0x80000000 ? $u - 0x100000000 : $u;
    }

    private function f32le(string $bin, int $off): float
    {
        return unpack('g', substr($bin, $off, 4))[1];
    }

    private function i8(string $bin, int $off): int
    {
        $v = ord($bin[$off]);

        return $v > 127 ? $v - 256 : $v;
    }
}
