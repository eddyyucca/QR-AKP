<?php

namespace App\Support;

use App\Models\QrCode;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use InvalidArgumentException;

/**
 * Membuat gambar QR bergaya AKP: biru tua, logo AKP di tengah, koreksi kesalahan tertinggi (H) supaya tetap
 * terbaca walau logo menutupi bagian tengah. Isi QR adalah alamat tujuan asli, tanpa pengalihan.
 */
class QrImage
{
    /** Templat warna untuk pengguna web: kode gelap di atas latar putih agar kontras tetap aman dipindai. [label, hex] */
    public const PRESETS = [
        'akp' => ['Biru AKP', null],
        'hitam' => ['Hitam', '111827'],
        'hijau' => ['Hijau tua', '065f46'],
        'merah' => ['Merah tua', '991b1b'],
        'ungu' => ['Ungu tua', '4c1d95'],
    ];

    /** Lebar logo terhadap lebar QR. Level koreksi H memulihkan hingga ~30% area; 0,32 menutup ~10%, masih jauh di bawah batas. */
    public const LOGO_RATIO = 0.32;

    public const FORMATS = ['png' => 'image/png', 'svg' => 'image/svg+xml'];

    /**
     * @param  array{fg?: string, bg?: string, logo?: bool, margin?: int}  $style  fg/bg heksadesimal 6 digit tanpa "#"
     * @return array{0: string, 1: string} [isi berkas, tipe MIME]
     */
    public static function render(QrCode $qr, string $format, int $size = 800, array $style = []): array
    {
        if (! isset(self::FORMATS[$format])) {
            throw new InvalidArgumentException("Format tidak dikenal: $format");
        }

        $logo = ($style['logo'] ?? true) ? public_path('assets/images/akp-qr.png') : '';
        $size = max(200, min($size, 2000));

        $builder = new Builder(
            writer: $format === 'svg' ? new SvgWriter() : new PngWriter(),
            data: $qr->target_url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: max(0, min((int) ($style['margin'] ?? 16), 100)),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: self::color($style['fg'] ?? null, [14, 42, 71]),
            backgroundColor: self::color($style['bg'] ?? null, [255, 255, 255]),
            logoPath: is_file($logo) ? $logo : '',
            logoResizeToWidth: is_file($logo) ? (int) round($size * self::LOGO_RATIO) : null,
            logoPunchoutBackground: false,
        );

        return [$builder->build()->getString(), self::FORMATS[$format]];
    }

    /** @param  list<int>  $default */
    private static function color(?string $hex, array $default): Color
    {
        if ($hex !== null && preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return new Color(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        }

        return new Color(...$default);
    }
}
