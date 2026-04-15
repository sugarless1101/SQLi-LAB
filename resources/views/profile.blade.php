@extends('layouts.app')

@section('title', 'プロフィール - SQLi Lab')

@section('content')
    <h1 class="text-2xl font-bold mb-4">プロフィール</h1>

    <div class="mb-6 p-4 bg-white border rounded">
        <p><span class="text-gray-500 text-sm">ユーザー名:</span> <span class="font-mono">{{ $user->username }}</span></p>
        <p><span class="text-gray-500 text-sm">メール:</span> <span class="font-mono">{{ $user->email }}</span></p>
    </div>

    <div class="mb-4">
        <p class="text-xs text-gray-500 mb-1">実行されたクエリ（学習用に表示）:</p>
        <pre class="bg-gray-900 text-green-300 text-xs p-3 rounded overflow-x-auto">{{ $postsSql }}</pre>
    </div>

    <h2 class="text-lg font-semibold mb-2">{{ $user->username }} さんの投稿一覧</h2>

    @if (count($posts) === 0)
        <p class="text-gray-600">投稿がありません。</p>
    @else
        <table class="w-full border-collapse bg-white shadow-sm">
            <thead class="bg-gray-100 text-left">
                <tr>
                    <th class="border px-3 py-2">ID</th>
                    <th class="border px-3 py-2">author</th>
                    <th class="border px-3 py-2">タイトル</th>
                    <th class="border px-3 py-2">本文</th>
                    <th class="border px-3 py-2">投稿日</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($posts as $post)
                    <tr>
                        <td class="border px-3 py-2">{{ $post->id ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $post->author ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $post->title ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $post->body ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $post->created_at ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
