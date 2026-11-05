@extends('errors.layout')

@section('title', 'Terjadi Kesalahan Server (500)')

@section('content')
    <div class="icon-circle" style="background-color: #fee2e2; color: #dc2626;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
    </div>

    <div class="badge" style="background-color: #fee2e2; color: #b91c1c;">
        Gangguan Server
    </div>

    <h1 class="error-code">500</h1>
    <h2 class="error-title">Terjadi Kesalahan pada Server</h2>
    <p class="error-message">
        Terjadi kendala internal pada sistem saat memproses permintaan Anda. Tim kami telah mencatat kendala ini dan sedang memperbaikinya.
    </p>
@endsection

