@extends('errors.layout')

@section('title', 'Sesi Kedaluwarsa (419)')

@section('content')
    <div class="icon-circle" style="background-color: #f1f5f9; color: #475569;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
    </div>

    <div class="badge" style="background-color: #f1f5f9; color: #334155;">
        Sesi Berakhir
    </div>

    <h1 class="error-code">419</h1>
    <h2 class="error-title">Sesi Halaman Kedaluwarsa</h2>
    <p class="error-message">
        Sesi keamanan Anda telah berakhir karena tidak ada aktivitas dalam beberapa waktu. Silakan kembali dan muat ulang halaman.
    </p>
@endsection

