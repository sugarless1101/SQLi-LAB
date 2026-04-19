@extends('layouts.app')

@section('title', 'ユーザー登録 - SQLi Lab')

@section('content')
    <h1 class="text-2xl font-bold mb-4">ユーザー登録</h1>

    @isset($created)
        <div class="mb-6 p-4 bg-green-50 border border-green-300 rounded">
            <p class="text-green-800">
                ユーザー「<span class="font-mono">{{ $created['username'] }}</span>」を登録しました。ID: {{ $created['id'] }}
            </p>
            <a
                href="/profile/{{ $created['id'] }}"
                class="inline-block mt-2 text-blue-600 hover:underline"
            >
                /profile/{{ $created['id'] }} を開く →
            </a>
        </div>
    @endisset

    <form method="POST" action="/register" class="space-y-4 max-w-3xl">
        @csrf
        <div>
            <label class="block text-sm mb-1" for="username">ユーザー名</label>
            <input
                type="text"
                id="username"
                name="username"
                class="w-full border border-gray-300 rounded px-3 py-2"
            >
        </div>
        <div>
            <label class="block text-sm mb-1" for="email">メールアドレス</label>
            <input
                type="text"
                id="email"
                name="email"
                class="w-full border border-gray-300 rounded px-3 py-2"
            >
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            登録
        </button>
    </form>
@endsection
