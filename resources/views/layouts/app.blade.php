<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SQLi Lab')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
    <header class="bg-gray-900 text-white">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/search" class="text-xl font-bold">SQLi Lab</a>
            <nav class="space-x-4 text-sm">
                <a href="/search" class="hover:underline">商品検索</a>
                <a href="/register" class="hover:underline">ユーザー登録</a>
            </nav>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <footer class="max-w-5xl mx-auto px-4 py-6 text-xs text-gray-500">
        ⚠️ 教育目的のため意図的に脆弱に作られています。本番環境では絶対に使用しないでください。
    </footer>
</body>
</html>
