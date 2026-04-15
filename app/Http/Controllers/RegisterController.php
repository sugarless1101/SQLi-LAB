<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// ユーザー登録コントローラ
// 演習対象: Second-order SQLi の入口。バリデーションなしでペイロードをそのまま保存する
class RegisterController extends Controller
{
    public function create()
    {
        return view('register');
    }

    public function store(Request $request)
    {
        $username = $request->input('username', '');
        $email    = $request->input('email', '');

        // VULNERABLE: バリデーションも escape もなしで INSERT。
        // ペイロードを文字列リテラルとして valid な形に整えればそのまま users.username に入る。
        // この値が後続の /profile/{id} で再利用されて Second-order SQLi が発火する。
        DB::insert(
            "INSERT INTO users (username, email, created_at) VALUES ('"
            . $username . "', '" . $email . "', datetime('now'))"
        );

        $id = DB::getPdo()->lastInsertId();

        return view('register', [
            'created' => [
                'id'       => $id,
                'username' => $username,
            ],
        ]);
    }
}
