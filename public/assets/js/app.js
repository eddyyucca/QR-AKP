// Penanda menu aktif ditentukan server sesuai halaman yang dibuka (lihat MenuService), bukan oleh klik.

// Layar "Memuat data...": muncul bila perpindahan halaman / pengiriman form lebih lama dari SHOW_AFTER ms,
// jadi halaman yang cepat tidak berkedip. Tautan unduhan diberi data-no-loading (atau href memuat /unduh) karena halaman tidak berganti.
(function () {
    const SHOW_AFTER = 400;
    const GIVE_UP_AFTER = 120000;           // jaga-jaga: layar tidak boleh menutup halaman selamanya
    let showTimer = null, giveUpTimer = null;

    const overlay = () => document.getElementById('pageLoading');

    function show() {
        clearTimeout(showTimer);
        showTimer = setTimeout(function () {
            const el = overlay();
            if (!el) return;
            el.classList.add('is-visible');
            el.setAttribute('aria-hidden', 'false');
            giveUpTimer = setTimeout(hide, GIVE_UP_AFTER);
        }, SHOW_AFTER);
    }

    function hide() {
        clearTimeout(showTimer);
        clearTimeout(giveUpTimer);
        const el = overlay();
        if (!el) return;
        el.classList.remove('is-visible');
        el.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const link = e.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-loading') || link.hasAttribute('data-bs-toggle')) return;
        if (link.target && link.target !== '_self') return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || /\/(unduh|export|download)(\/|$|\?)/.test(url.pathname)) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;      // loncat ke anchor di halaman yang sama

        show();
    });

    document.addEventListener('submit', function (e) {
        const form = e.target;
        // Listener lain (konfirmasi, AJAX) bisa membatalkan pengiriman; periksa setelah semuanya selesai.
        setTimeout(function () {
            if (e.defaultPrevented || form.target === '_blank' || form.hasAttribute('data-no-loading')) return;
            show();
        }, 0);
    });

    // Filter yang mengirim form lewat form.submit() (mis. onchange pada dropdown) tidak memicu event submit.
    const nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if (this.target !== '_blank' && !this.hasAttribute('data-no-loading')) show();
        return nativeSubmit.call(this);
    };

    // Kembali dengan tombol back (halaman dari cache browser) tidak boleh menampilkan layar ini lagi.
    window.addEventListener('pageshow', hide);
})();
