<?php

namespace App\Services\Challenge;

use App\Models\TradingAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ChallengeCertificateGenerator
{
    private const TEMPLATE_CANDIDATES = [
        'challenge.png',
        'challenge.jpeg',
        'challenge.jpg',
    ];

    /**
     * @return array{disk:string,path:string,name:string,absolute_path:string}|null
     */
    public function ensureForAccount(TradingAccount $account, bool $force = false): ?array
    {
        if ($account->challenge_status !== 'passed') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $force && filled($account->certificate_path) && $disk->exists((string) $account->certificate_path)) {
            return $this->attachmentPayload($account, (string) $account->certificate_path);
        }

        if ($force && filled($account->certificate_path) && $disk->exists((string) $account->certificate_path)) {
            $disk->delete((string) $account->certificate_path);
        }

        $templatePath = $this->templatePath();
        $image = $this->createImageFromTemplate($templatePath);

        try {
            $this->drawCertificateFields($image, $account);

            $certificatePath = $this->certificatePath($account);
            $directory = dirname($certificatePath);

            if (! $disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }

            $absolutePath = $disk->path($certificatePath);

            if (! imagepng($image, $absolutePath, 9)) {
                throw new RuntimeException('Unable to write generated certificate image.');
            }
        } finally {
            imagedestroy($image);
        }

        $account->forceFill([
            'certificate_path' => $certificatePath,
            'certificate_generated_at' => now(),
        ])->save();

        return $this->attachmentPayload($account, $certificatePath);
    }

    private function templatePath(): string
    {
        foreach (self::TEMPLATE_CANDIDATES as $candidate) {
            $path = public_path($candidate);

            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Certificate template is missing. Expected public/challenge.png or public/challenge.jpeg.');
    }

    /**
     * @return resource|\GdImage
     */
    private function createImageFromTemplate(string $templatePath)
    {
        $extension = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));

        $image = match ($extension) {
            'png' => imagecreatefrompng($templatePath),
            'jpg', 'jpeg' => imagecreatefromjpeg($templatePath),
            default => false,
        };

        if (! $image) {
            throw new RuntimeException('Certificate template could not be loaded by GD.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * @param  resource|\GdImage  $image
     */
    private function drawCertificateFields($image, TradingAccount $account): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scaleX = $width / 1536;
        $scaleY = $height / 1024;
        $scale = min($scaleX, $scaleY);
        $boldFont = $this->fontPath(true);
        $regularFont = $this->fontPath(false);
        $name = trim((string) ($account->user?->name ?: 'Wolforix Trader'));
        $date = $this->passedDate($account)->format('Y-m-d');
        $plan = $this->certificatePlanLabel($account);
        $gold = $this->color($image, 230, 188, 99);
        $white = $this->color($image, 246, 247, 250);
        $muted = $this->color($image, 214, 218, 226);
        $cover = $this->color($image, 7, 8, 9, 12);
        $softCover = $this->color($image, 7, 8, 9, 26);

        $this->roundedRectangle($image, $this->sx(96, $scaleX), $this->sy(329, $scaleY), $this->sx(338, $scaleX), $this->sy(374, $scaleY), $this->sx(22, $scaleX), $softCover);
        $this->roundedBorder($image, $this->sx(96, $scaleX), $this->sy(329, $scaleY), $this->sx(338, $scaleX), $this->sy(374, $scaleY), $this->sx(22, $scaleX), $this->color($image, 207, 167, 78, 52));
        $this->drawFittedText($image, $plan, $boldFont, $this->sx(112, $scaleX), $this->sy(359, $scaleY), $this->sx(210, $scaleX), $this->fontSize(22, $scale), $this->fontSize(12, $scale), $gold, 'center');

        $this->filledRectangle($image, $this->sx(94, $scaleX), $this->sy(436, $scaleY), $this->sx(760, $scaleX), $this->sy(492, $scaleY), $cover);
        $this->drawFittedText($image, $name, $boldFont, $this->sx(99, $scaleX), $this->sy(474, $scaleY), $this->sx(650, $scaleX), $this->fontSize(34, $scale), $this->fontSize(18, $scale), $white);

        $this->filledRectangle($image, $this->sx(360, $scaleX), $this->sy(610, $scaleY), $this->sx(570, $scaleX), $this->sy(648, $scaleY), $cover);
        $this->drawFittedText($image, $plan, $boldFont, $this->sx(373, $scaleX), $this->sy(636, $scaleY), $this->sx(180, $scaleX), $this->fontSize(21, $scale), $this->fontSize(11, $scale), $gold);

        $this->filledRectangle($image, $this->sx(136, $scaleX), $this->sy(877, $scaleY), $this->sx(404, $scaleX), $this->sy(924, $scaleY), $cover);
        $this->drawFittedText($image, $date, $regularFont, $this->sx(149, $scaleX), $this->sy(910, $scaleY), $this->sx(235, $scaleX), $this->fontSize(24, $scale), $this->fontSize(14, $scale), $muted);
    }

    private function passedDate(TradingAccount $account): Carbon
    {
        if ($account->passed_at instanceof \DateTimeInterface) {
            return Carbon::instance($account->passed_at);
        }

        return now();
    }

    private function certificatePlanLabel(TradingAccount $account): string
    {
        $challengeType = (string) ($account->challenge_type ?: 'challenge');
        $typeLabel = (string) (config("wolforix.challenge_catalog.{$challengeType}.label") ?: str($challengeType)->replace('_', ' ')->title());
        $size = (int) ($account->account_size ?: $account->starting_balance ?: 0);
        $sizeLabel = $size > 0 ? ((int) ($size / 1000)).'K' : '';

        return trim($typeLabel.($sizeLabel !== '' ? ' / '.$sizeLabel : ''));
    }

    private function certificatePath(TradingAccount $account): string
    {
        $reference = (string) ($account->account_reference ?: 'account-'.$account->id);
        $hash = substr(hash_hmac('sha256', $reference.'|'.$account->id, (string) config('app.key', 'wolforix')), 0, 12);
        $filename = Str::slug($reference) ?: 'account-'.$account->id;

        return "certificates/{$account->id}/{$filename}-{$hash}.png";
    }

    /**
     * @return array{disk:string,path:string,name:string,absolute_path:string}
     */
    private function attachmentPayload(TradingAccount $account, string $path): array
    {
        return [
            'disk' => 'public',
            'path' => $path,
            'name' => 'wolforix-certificate-'.Str::slug((string) ($account->account_reference ?: $account->id)).'.png',
            'absolute_path' => Storage::disk('public')->path($path),
        ];
    }

    private function fontPath(bool $bold): ?string
    {
        $candidates = $bold
            ? [
                resource_path('fonts/Wolforix-Bold.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/Library/Fonts/Arial Bold.ttf',
            ]
            : [
                resource_path('fonts/Wolforix-Regular.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/Library/Fonts/Arial.ttf',
            ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  resource|\GdImage  $image
     */
    private function drawFittedText($image, string $text, ?string $font, int $x, int $baseline, int $maxWidth, int $maxSize, int $minSize, int $color, string $align = 'left'): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        if ($font === null) {
            imagestring($image, 5, $x, $baseline - 18, $text, $color);

            return;
        }

        $size = $maxSize;

        while ($size > $minSize && $this->textWidth($text, $font, $size) > $maxWidth) {
            $size--;
        }

        $textX = $x;

        if ($align === 'center') {
            $textX = $x + (int) max(($maxWidth - $this->textWidth($text, $font, $size)) / 2, 0);
        }

        imagettftext($image, $size, 0, $textX, $baseline, $color, $font, $text);
    }

    private function textWidth(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : abs((int) $box[2] - (int) $box[0]);
    }

    /**
     * @param  resource|\GdImage  $image
     */
    private function roundedRectangle($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    /**
     * @param  resource|\GdImage  $image
     */
    private function roundedBorder($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
        imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
        imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
        imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagearc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
        imagearc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
        imagearc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
        imagearc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
    }

    /**
     * @param  resource|\GdImage  $image
     */
    private function filledRectangle($image, int $x1, int $y1, int $x2, int $y2, int $color): void
    {
        imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color);
    }

    /**
     * @param  resource|\GdImage  $image
     */
    private function color($image, int $red, int $green, int $blue, int $alpha = 0): int
    {
        return imagecolorallocatealpha($image, $red, $green, $blue, max(min($alpha, 127), 0));
    }

    private function sx(int $value, float $scale): int
    {
        return (int) round($value * $scale);
    }

    private function sy(int $value, float $scale): int
    {
        return (int) round($value * $scale);
    }

    private function fontSize(int $value, float $scale): int
    {
        return max((int) round($value * $scale), 1);
    }
}
