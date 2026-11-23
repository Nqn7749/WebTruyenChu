<?php

namespace Database\Seeders;

use App\Models\Ad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Sample ads for trying the side rails and the ad admin in local development: one image ad per
 * placement plus a few in other states (stacked, switched off, scheduled, expired, code).
 * Safe to run again: ads are matched by name. Remove them with DemoDataCleanupSeeder.
 */
class AdSeeder extends Seeder
{
    public const PREFIX = '[Demo] ';

    public function run(): void
    {
        if (! function_exists('imagepng')) {
            $this->command?->warn('PHP GD (PNG) is required to draw the sample banners.');

            return;
        }

        $palette = [
            'home_left' => [[14, 116, 144], [8, 51, 68]],
            'home_right' => [[190, 24, 93], [76, 5, 25]],
            'story_left' => [[22, 163, 74], [5, 46, 22]],
            'story_right' => [[217, 119, 6], [69, 26, 3]],
            'chapter_left' => [[79, 70, 229], [30, 27, 75]],
            'chapter_right' => [[220, 38, 38], [69, 10, 10]],
            'list_left' => [[8, 145, 178], [8, 47, 73]],
            'list_right' => [[124, 58, 237], [46, 16, 101]],
        ];

        $order = 0;
        foreach (Ad::PLACEMENTS as $placement => $info) {
            $this->image($placement.'-1', $placement, 'Banner '.$info['page'].' '.$info['side'], $palette[$placement], ++$order);
        }

        // A second ad in the same edge to see two stacked (priority 5 shows after priority 0).
        $this->image('home_left-2', 'home_left', 'Banner trang chủ trái (thứ 2)', [[234, 88, 12], [67, 20, 7]], 5);

        // Other states, to see them in the admin list and to check that they do NOT show on the site.
        $this->image('off', 'home_right', 'Banner đã tắt', [[100, 116, 139], [15, 23, 42]], 9, ['is_active' => false]);
        $this->image('scheduled', 'story_right', 'Banner chưa tới giờ', [[13, 148, 136], [4, 47, 46]], 9, ['starts_at' => now()->addDays(3)]);
        $this->image('expired', 'chapter_left', 'Banner hết hạn', [[161, 98, 7], [66, 32, 6]], 9, ['ends_at' => now()->subDay()]);

        // A code ad, the way an ad network snippet would be pasted.
        Ad::updateOrCreate(['name' => self::PREFIX.'Mã quảng cáo mẫu'], [
            'placement' => 'list_right',
            'type' => 'code',
            'html_code' => '<div style="background:#fef3c7;color:#78350f;border:2px dashed #d97706;padding:14px 8px;text-align:center;font:600 13px/1.4 system-ui">Mã quảng cáo mẫu<br><small>(dán từ mạng quảng cáo)</small></div>',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $this->command?->info('Demo ads ready: '.Ad::query()->where('name', 'like', self::PREFIX.'%')->count());
    }

    /**
     * @param  array{0: array{int,int,int}, 1: array{int,int,int}}  $colors  top and bottom of the gradient
     * @param  array<string, mixed>  $extra
     */
    private function image(string $key, string $placement, string $label, array $colors, int $order, array $extra = []): void
    {
        $path = 'ads/demo-'.$key.'.png';
        Storage::disk('public')->put($path, $this->banner($label, $colors));

        Ad::updateOrCreate(['name' => self::PREFIX.$label], array_merge([
            'placement' => $placement,
            'type' => 'image',
            'image_path' => $path,
            'link_url' => 'https://example.com/quang-cao-thu?vi-tri='.$placement,
            'alt_text' => $label,
            'is_active' => true,
            'sort_order' => $order,
        ], $extra));
    }

    /**
     * A 160 x 600 gradient banner with the label drawn large (GD's built-in font, scaled up).
     */
    private function banner(string $label, array $colors): string
    {
        [$width, $height] = [160, 600];
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / ($height - 1);
            $color = imagecolorallocate(
                $image,
                (int) ($colors[0][0] + ($colors[1][0] - $colors[0][0]) * $t),
                (int) ($colors[0][1] + ($colors[1][1] - $colors[0][1]) * $t),
                (int) ($colors[0][2] + ($colors[1][2] - $colors[0][2]) * $t),
            );
            imageline($image, 0, $y, $width, $y, $color);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        imagerectangle($image, 3, 3, $width - 4, $height - 4, $white);

        // GD's built-in font 5 is 9px wide, so about 16 characters fit on a 160px banner.
        imagestring($image, 5, 40, 40, 'QUANG CAO', $white);
        imagestring($image, 5, 62, 60, 'THU', $white);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label) ?: $label;
        $lines = wordwrap(preg_replace('/[^\x20-\x7E]/', '', $ascii) ?? '', 15, "\n", true);
        foreach (explode("\n", $lines) as $i => $line) {
            imagestring($image, 4, 12, 120 + $i * 20, $line, $white);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
