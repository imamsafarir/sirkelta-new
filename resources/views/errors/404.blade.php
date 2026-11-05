@extends('errors.layout')

@section('title', 'Halaman Tidak Ditemukan (404)')

@section('content')
    <div class="icon-circle" style="background-color: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M16 16s-1.5-2-4-2-4 2-4 2"></path>
            <line x1="9" y1="9" x2="9.01" y2="9"></line>
            <line x1="15" y1="9" x2="15.01" y2="9"></line>
        </svg>
    </div>

    <div class="badge" style="background-color: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary); border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);">
        Error 404
    </div>

    <h1 class="error-code">404</h1>
    <h2 class="error-title">Halaman Tidak Ditemukan</h2>
    <p class="error-message">
        Maaf, tautan yang Anda tuju tidak tersedia, salah ketik, atau telah dipindahkan ke alamat baru.
    </p>

    <!-- 5 Seconds Countdown Notification -->
    <div class="countdown-pill" id="countdown-pill">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        <span>Otomatis kembali dalam <strong id="countdown-sec">5</strong> detik</span>
    </div>
@endsection

@section('scripts')
<script>
    (function() {
        var secondsLeft = 5;
        var countdownDisplay = document.getElementById('countdown-sec');
        var btnBackLabel = document.getElementById('btn-back-label');

        if (btnBackLabel) {
            btnBackLabel.textContent = 'Kembali ke Halaman Sebelumnya (' + secondsLeft + 's)';
        }

        var timer = setInterval(function() {
            secondsLeft--;
            if (countdownDisplay) {
                countdownDisplay.textContent = secondsLeft;
            }
            if (btnBackLabel) {
                btnBackLabel.textContent = 'Kembali ke Halaman Sebelumnya (' + secondsLeft + 's)';
            }
            if (secondsLeft <= 0) {
                clearInterval(timer);
                smartGoBack();
            }
        }, 1000);
    })();
</script>
@endsection
