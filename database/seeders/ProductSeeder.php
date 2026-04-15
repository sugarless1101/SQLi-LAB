<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('products')->insert([
            ['name' => 'ボールペン',   'category' => '文房具',   'price' => 150,  'stock' => 100, 'description' => '書きやすいボールペン'],
            ['name' => 'ノート',       'category' => '文房具',   'price' => 300,  'stock' => 50,  'description' => 'A4サイズ方眼ノート'],
            ['name' => 'チョコレート', 'category' => '食品',     'price' => 200,  'stock' => 80,  'description' => 'ミルクチョコレート'],
            ['name' => 'コーヒー豆',   'category' => '食品',     'price' => 800,  'stock' => 30,  'description' => 'コロンビア産'],
            ['name' => 'USBメモリ',    'category' => '電子機器', 'price' => 1200, 'stock' => 25,  'description' => '32GB USB3.0'],
            ['name' => 'マウス',       'category' => '電子機器', 'price' => 2500, 'stock' => 15,  'description' => 'ワイヤレスマウス'],
            ['name' => '消しゴム',     'category' => '文房具',   'price' => 80,   'stock' => 200, 'description' => 'よく消える消しゴム'],
            ['name' => '緑茶',         'category' => '食品',     'price' => 150,  'stock' => 60,  'description' => '静岡産緑茶'],
        ]);
    }
}
