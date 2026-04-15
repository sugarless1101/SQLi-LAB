@extends('layouts.app')

@section('title', '商品検索 - SQLi Lab')

@section('content')
    <h1 class="text-2xl font-bold mb-4">商品検索</h1>

    <form method="GET" action="/search" class="mb-6 flex gap-2">
        <input
            type="text"
            name="keyword"
            value="{{ $keyword }}"
            placeholder="商品名で検索"
            class="flex-1 border border-gray-300 rounded px-3 py-2"
        >
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            検索
        </button>
    </form>

    <div class="mb-4">
        <p class="text-xs text-gray-500 mb-1">実行されたクエリ（学習用に表示）:</p>
        <pre class="bg-gray-900 text-green-300 text-xs p-3 rounded overflow-x-auto">{{ $sql }}</pre>
    </div>

    @if (count($results) === 0)
        <p class="text-gray-600">該当する商品がありません。</p>
    @else
        <table class="w-full border-collapse bg-white shadow-sm">
            <thead class="bg-gray-100 text-left">
                <tr>
                    <th class="border px-3 py-2">ID</th>
                    <th class="border px-3 py-2">商品名</th>
                    <th class="border px-3 py-2">カテゴリ</th>
                    <th class="border px-3 py-2">価格</th>
                    <th class="border px-3 py-2">在庫</th>
                    <th class="border px-3 py-2">説明</th>
                    <th class="border px-3 py-2">作成日時</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results as $row)
                    <tr>
                        <td class="border px-3 py-2">{{ $row->id ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $row->name ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $row->category ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $row->price ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $row->stock ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $row->description ?? '' }}</td>
                        <td class="border px-3 py-2">{{ $row->created_at ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
