# CLAUDE.md - sqli-lab

## プロジェクト概要
Zenn記事「Burp Suiteで学ぶSQLインジェクション入門」の教材用やられアプリ。
Laravel + SQLiteで構築し、意図的にSQLインジェクション脆弱性を含む。

**⚠️ 教育目的で意図的に脆弱に作られています。絶対に本番環境にデプロイしないこと。**

## 技術スタック
- PHP 8.2+ / Laravel 11 / SQLite / Blade + Tailwind CDN

## 意図的に脆弱にするルール
- SQLi対象クエリは **DB::select() で生SQL + 文字列結合**。Eloquent/QueryBuilder/プレースホルダ禁止
- 脆弱箇所には必ず `// VULNERABLE: [理由]` コメントを付ける
- 脆弱でない箇所（ルーティング等）はLaravel標準の書き方に従う
- APP_DEBUG=true でエラー詳細を表示する

## 機能一覧（3画面）

### 1. 商品検索 GET /search（UNION-based / Blind SQLi）
- SearchController@index
- クエリ: `SELECT * FROM products WHERE name LIKE '%{keyword}%'`（文字列結合）
- 演習内容:
  - UNION-based: カラム数特定 → sqlite_version() → sqlite_masterからスキーマ抽出
  - Boolean-based Blind: AND 1=1 / AND 1=2 でレスポンス差分
  - Time-based Blind: SQLiteの重い関数で遅延を擬似再現

### 2. ユーザー登録 GET|POST /register（Second-order 入口）
- RegisterController@create, store
- クエリ: `INSERT INTO users (username, email) VALUES ('{username}', '{email}')`
- バリデーションなし、ペイロードをそのままINSERT

### 3. プロフィール GET /profile/{id}（Second-order 発火点）
- ProfileController@show
- DBから取得したusernameを別クエリに文字列結合:
  `SELECT * FROM posts WHERE author = '{username}'`
- 登録時に仕込んだペイロードがここで発火

## ディレクトリ構成
```
sqli-lab/
├── CLAUDE.md
├── docs/DESIGN.md
├── app/Http/Controllers/
│   ├── SearchController.php
│   ├── RegisterController.php
│   └── ProfileController.php
├── database/
│   ├── migrations/
│   │   ├── create_products_table.php
│   │   ├── create_users_table.php
│   │   └── create_posts_table.php
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

## コーディング規約
- 1機能1コントローラ、Fat Controller可（教材なので簡潔さ優先）
- ビューはTailwind CDNで最低限の体裁。凝ったデザイン不要
- 日本語コメントを積極的に使う（記事読者向け）
- 各コントローラ冒頭にどの攻撃手法の演習用かコメント明記

## テスト方針
自動テスト不要。以下を手動 + Burp Suite で確認:
- [ ] /search で通常検索が動作
- [ ] /search で UNION-based SQLi 成功
- [ ] /search で Boolean-based Blind の差分確認可
- [ ] /register でペイロード入りusername登録可
- [ ] /profile/{id} で Second-order SQLi 発火

## 開発コマンド
```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
# リセット: php artisan migrate:fresh --seed
```

## .env（SQLite用に変更する箇所）
```
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
APP_DEBUG=true
```

## 禁止事項
- 演習対象クエリで Eloquent の where() やプレースホルダを使わない
- Laravel標準の users マイグレーションは使わない（カスタムで作る）
- 認証パッケージ（Breeze等）は入れない