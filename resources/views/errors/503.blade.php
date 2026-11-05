@extends('errors.layout')

@section('title', 'Sedang Dalam Pemeliharaan (503)')

@section('content')
    <div class="icon-circle" style="background-color: #f0fdf4; color: #16a34a;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
        </svg>
    </div>

    <div class="badge" style="background-color: #f0fdf4; color: #15803d;">
        Pemeliharaan Sistem
    </div>

    <h1 class="error-code">503</h1>
    <h2 class="error-title">Sistem Sedang Diperbarui</h2>
    <p class="error-message">
        Layanan sedang dalam proses pemeliharaan rutin atau pembaruan fitur demi kenyamanan Anda. Kami akan segera kembali dalam beberapa saat.
    </p>
@endsection

