<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tree = [
            'Tiên Hiệp'   => ['Tiên Hiệp Trọng Sinh', 'Tiên Hiệp Hệ Thống'],
            'Kiếm Hiệp'   => [],
            'Ngôn Tình'   => ['Ngôn Tình Hiện Đại', 'Ngôn Tình Cổ Đại'],
            'Đô Thị'      => [],
            'Huyền Huyễn' => [],
            'Khoa Huyễn'  => [],
            'Trinh Thám'  => [],
            'Quân Sự'     => [],
            'Light Novel' => [],
            'Đồng Nhân'   => [],
        ];

        foreach ($tree as $parentName => $children) {
            $parent = Category::create([
                'name'        => $parentName,
                'slug'        => Str::slug($parentName),
                'description' => "Truyện thuộc thể loại {$parentName}.",
            ]);

            foreach ($children as $childName) {
                Category::create([
                    'parent_id'   => $parent->id,
                    'name'        => $childName,
                    'slug'        => Str::slug($childName),
                    'description' => "Truyện thuộc thể loại {$childName}.",
                ]);
            }
        }
    }
}