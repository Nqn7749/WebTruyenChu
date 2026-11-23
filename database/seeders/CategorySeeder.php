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
            'Tiên Hiệp' => ['Tiên Hiệp Trọng Sinh', 'Tiên Hiệp Hệ Thống'],
            'Kiếm Hiệp' => [],
            'Ngôn Tình' => ['Ngôn Tình Hiện Đại', 'Ngôn Tình Cổ Đại'],
            'Đô Thị' => [],
            'Huyền Huyễn' => [],
            'Khoa Huyễn' => [],
            'Trinh Thám' => [],
            'Quân Sự' => [],
            'Light Novel' => [],
            'Đồng Nhân' => [],
            'Xuyên Không' => [],
            'Trọng Sinh' => [],
            'Võng Du' => [],
            'Lịch Sử' => [],
            'Dị Giới' => [],
            'Mạt Thế' => [],
            'Linh Dị' => [],
            'Kinh Dị' => [],
            'Hệ Thống' => [],
            'Cung Đấu' => [],
            'Gia Đấu' => [],
            'Học Đường' => [],
            'Hài Hước' => [],
            'Thể Thao' => [],
            'Cổ Đại' => [],
            'Điền Văn' => [],
            'Đam Mỹ' => [],
            'Bách Hợp' => [],
            'Truyện Teen' => [],
            'Phương Tây' => [],
        ];

        // Idempotent: safe to re-run on a database that already has categories.
        foreach ($tree as $parentName => $children) {
            $parent = Category::firstOrCreate(
                ['slug' => Str::slug($parentName)],
                ['name' => $parentName, 'description' => "Truyện thuộc thể loại {$parentName}."],
            );

            foreach ($children as $childName) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($childName)],
                    ['parent_id' => $parent->id, 'name' => $childName, 'description' => "Truyện thuộc thể loại {$childName}."],
                );
            }
        }
    }
}
