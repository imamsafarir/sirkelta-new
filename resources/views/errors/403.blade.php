@extends('errors.layout')

@section('title', 'Akses Ditolak (403)')

@section('content')
    <div class="icon-circle" style="background-color: #fef3c7; color: #d97706;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
    </div>

    <div class="badge" style="background-color: #fef3c7; color: #b45309;">
        Akses Ditolak
    </div>

    <h1 class="error-code">403</h1>
    <h2 class="error-title">Anda Tidak Memiliki Izin Akses</h2>
    <p class="error-message">
        Halaman atau menu ini dibatasi khusus untuk tingkatan akun atau role tertentu. Silakan hubungi pengelola jika Anda memerlukan akses.
    </p>
@endsection

