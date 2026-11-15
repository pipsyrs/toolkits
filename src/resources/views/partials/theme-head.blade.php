{{-- Tema awal sebelum CSS (cegah flash). CSP: nonce otomatis bila Vite::useCspNonce() aktif. --}}
<script @if(Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>
    (function () {
        var m = 'system';
        try { m = localStorage.getItem('theme') || 'system'; } catch (e) {}
        if (m !== 'light' && m !== 'dark') m = 'system';
        var t = m === 'system' ? (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : m;
        var d = document.documentElement;
        d.dataset.theme = t;
        d.dataset.themeMode = m;
        d.style.colorScheme = t;
    })();
</script>
