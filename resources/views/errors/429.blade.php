@extends('errors.layout')

@section('title', 'Terlalu Banyak Permintaan (429)')

@section('content')
    <div class="icon-circle" style="background-color: #fff7ed; color: #ea580c;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
        </svg>
    </div>

    <div class="badge" style="background-color: #fff7ed; color: #c2410c;">
        Batas Frekuensi
    </div>

    <h1 class="error-code">429</h1>
    <h2 class="error-title">Terlalu Banyak Permintaan</h2>
    <p class="error-message">
        Sistem mendeteksi terlalu banyak permintaan dalam waktu singkat. Mohon tunggu beberapa detik sebelum mencoba kembali.
    </p>
@endsection

