<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// 商品検索コントローラ
// 演習対象: UNION-based SQLi / Boolean-based Blind / Time-based Blind
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->input('keyword', '');

        // VULNERABLE: ユーザー入力を直接文字列結合して LIKE 句に渡している。
        // sqli 演習の本丸。プレースホルダを使わないため UNION / Blind が成立する。
        $sql = "SELECT * FROM products WHERE name LIKE '%" . $keyword . "%'";

        // 注意: try/catch しない。SQL エラーは APP_DEBUG=true でそのまま画面に出して
        // 学習者がエラーメッセージから情報を抜く演習を行えるようにする。
        $results = DB::select($sql);

        return view('search', compact('keyword', 'results', 'sql'));
    }
}
