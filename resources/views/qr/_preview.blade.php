{{-- Pratinjau QR langsung: dipasang di Beranda dan halaman Buat QR. Tidak menyimpan apa pun. --}}
@push('scripts')
<script>
(function () {
    const input = document.getElementById('target_url');
    const box = document.getElementById('previewBox');
    const hint = document.getElementById('previewHint');
    if (!input || !box) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let timer = null, seq = 0;

    function show(message) { hint.textContent = message; }

    async function refresh() {
        const value = input.value.trim();
        if (!value) { box.innerHTML = '<i class="bi bi-qr-code"></i>'; box.classList.remove('has-qr'); show('Isi alamat tujuan untuk melihat pratinjau QR.'); return; }
        const mine = ++seq;
        show('Membuat pratinjau...');
        try {
            const res = await fetch(@json(route('qr.preview')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ target_url: value }),
            });
            if (mine !== seq) return;
            const body = await res.json();
            if (!res.ok) {
                box.innerHTML = '<i class="bi bi-qr-code"></i>'; box.classList.remove('has-qr');
                show(body.errors?.target_url?.[0] ?? 'Alamat tujuan belum valid.');
                return;
            }
            const img = new Image();
            img.alt = 'Pratinjau QR'; img.src = body.image;
            box.replaceChildren(img); box.classList.add('has-qr');
            show('Pratinjau. QR akan mengarah langsung ke alamat ini.');
        } catch (e) {
            if (mine === seq) show('Pratinjau tidak tersedia saat ini. Anda tetap bisa membuat QR.');
        }
    }

    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(refresh, 450); });
    if (input.value.trim()) refresh();
})();
</script>
@endpush

@push('styles')
<style>
    .preview-box { width: 100%; max-width: 260px; aspect-ratio: 1; margin-inline: auto; display: grid; place-items: center;
        background: #fff; border: 2px dashed #cbd5e1; border-radius: 16px; color: #94a3b8; font-size: 4rem; overflow: hidden; }
    .preview-box.has-qr { border: 1px solid #e2e8f0; padding: 6px; }
    .preview-box img { width: 100%; height: 100%; object-fit: contain; }
</style>
@endpush
