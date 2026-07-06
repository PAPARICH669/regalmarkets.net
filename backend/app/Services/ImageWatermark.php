<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Stamps a tiled, semi-transparent "REGAL MARKETS" watermark onto uploaded KYC
 * images (ID + selfie) so a leaked copy can never be reused as a genuine
 * document elsewhere. Uses ImageMagick (installed on the server). Best-effort:
 * PDFs and any failure return false — the caller decides whether that blocks
 * the submission.
 */
class ImageWatermark
{
    /** Watermark a raster image in place. Returns true on success. */
    public static function stamp(string $absPath, string $label): bool
    {
        if (! is_file($absPath)) return false;

        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        if ($ext === 'pdf') return false; // cannot safely raster-stamp a PDF here

        $convert  = self::binary('convert');
        $identify = self::binary('identify');
        if (! $convert || ! $identify) return false;

        $tile = $out = null;
        try {
            $dim = self::run([$identify, '-format', '%wx%h', $absPath . '[0]']);
            if ($dim === null || ! preg_match('/^(\d+)x(\d+)$/', trim($dim), $m)) return false;
            [$w, $h] = [(int) $m[1], (int) $m[2]];
            if ($w < 40 || $h < 40) return false;

            $pt    = max(14, (int) round($w / 22));
            $tileW = max(220, $pt * 14);
            $tileH = max(110, $pt * 6);

            $tile = tempnam(sys_get_temp_dir(), 'wmt') . '.png';
            if (self::run([
                $convert, '-size', "{$tileW}x{$tileH}", 'xc:none',
                '-gravity', 'center', '-fill', 'rgba(255,255,255,0.33)',
                '-pointsize', (string) $pt, '-annotate', '-30', $label, $tile,
            ]) === null) return false;

            $out = tempnam(sys_get_temp_dir(), 'wmo') . '.' . ($ext ?: 'jpg');
            if (self::run([
                $convert, $absPath,
                '(', '-size', "{$w}x{$h}", "tile:{$tile}", ')',
                '-gravity', 'center', '-compose', 'over', '-composite', $out,
            ]) === null) return false;

            if (is_file($out) && filesize($out) > 0) {
                rename($out, $absPath);
                $out = null;
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            Log::warning('KYC watermark failed: ' . $e->getMessage());
            return false;
        } finally {
            if ($tile && is_file($tile)) @unlink($tile);
            if ($out && is_file($out))  @unlink($out);
        }
    }

    /** Locate an ImageMagick binary (magick sub-command or legacy convert/identify). */
    protected static function binary(string $tool): ?string
    {
        foreach (["/usr/bin/{$tool}", "/usr/local/bin/{$tool}", $tool] as $cand) {
            $probe = self::run([$cand, '-version']);
            if ($probe !== null) return $cand;
        }
        // ImageMagick 7: `magick convert ...` / `magick identify ...`
        $magick = self::run(['magick', '-version']);
        if ($magick !== null) return "magick {$tool}";
        return null;
    }

    /** Run a command (args escaped); return stdout on exit 0, else null. */
    protected static function run(array $args): ?string
    {
        $cmd = implode(' ', array_map('escapeshellarg', $args));
        // "magick convert" is passed as one arg above; un-escape that one case.
        $cmd = preg_replace("/^'magick (convert|identify)'/", 'magick $1', $cmd);
        $output = []; $code = 1;
        exec($cmd . ' 2>/dev/null', $output, $code);
        return $code === 0 ? implode("\n", $output) : null;
    }
}
