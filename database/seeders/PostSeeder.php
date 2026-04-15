<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        // admin の投稿を 2 件用意することで、Second-order SQLi のペイロードが
        // 'admin' OR '1'='1 のように展開された際に「全件取れている」感が出る
        DB::table('posts')->insert([
            ['author' => 'admin',  'title' => 'テスト投稿1',  'body' => 'これはテスト投稿です。'],
            ['author' => 'admin',  'title' => 'テスト投稿2',  'body' => '管理者の2つ目の投稿。'],
            ['author' => 'tanaka', 'title' => '商品レビュー', 'body' => 'ボールペンが良かった。'],
        ]);
    }
}
