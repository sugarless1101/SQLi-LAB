<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

// プロフィール表示コントローラ
// 演習対象: Second-order SQLi の発火点。
// ID 取得側はあえてプレースホルダで安全にしておき、「DB から取り出した値を信頼して
// 再度文字列結合する」という現実的アンチパターンを再現する。
class ProfileController extends Controller
{
    public function show($id)
    {
        // ここはプレースホルダで安全。第 1 クエリは攻撃面ではない。
        $user = DB::select("SELECT * FROM users WHERE id = ?", [$id]);

        if (empty($user)) {
            abort(404);
        }

        $username = $user[0]->username;

        // VULNERABLE: DB から取り出した username を信頼して文字列結合している。
        // 登録時に仕込まれた SQL ペイロードがここで実行される（Second-order SQLi）。
        $postsSql = "SELECT * FROM posts WHERE author = '" . $username . "'";
        $posts = DB::select($postsSql);

        return view('profile', [
            'user'     => $user[0],
            'posts'    => $posts,
            'postsSql' => $postsSql,
        ]);
    }
}
