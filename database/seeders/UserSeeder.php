<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // チャレンジ演習「問題1: users テーブルの全 username を抽出せよ」用の
        // サンプルユーザー。UNION-based SQLi で username カラムが取得できることを確認できる。
        DB::table('users')->insert([
            ['username' => 'admin',  'email' => 'admin@example.com',  'created_at' => now()],
            ['username' => 'tanaka', 'email' => 'tanaka@example.com', 'created_at' => now()],
            ['username' => 'guest',  'email' => 'guest@example.com',  'created_at' => now()],
        ]);
    }
}
