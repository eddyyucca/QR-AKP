<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Rules\PublicWebUrl;
use App\Support\QrImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QrCodeController extends Controller
{
    private function isAdmin(): bool
    {
        return portal_user()->hasRole('admin');
    }

    /** Cari QR yang boleh dilihat pengguna ini. QR milik orang lain dijawab 404, bukan 403, supaya keberadaannya tidak bocor. */
    private function findVisible(string $uuid): QrCode
    {
        return QrCode::visibleTo(portal_user())->where('uuid', $uuid)->firstOrFail();
    }

    private function stats(): array
    {
        $user = portal_user();

        return [
            'total' => QrCode::visibleTo($user)->count(),
            'mine' => QrCode::where('creator_portal_id', (string) $user->id)->count(),
            'today' => QrCode::visibleTo($user)->whereDate('created_at', today())->count(),
            'week' => QrCode::visibleTo($user)->where('created_at', '>=', now()->subDays(7))->count(),
            'creators' => $this->isAdmin() ? QrCode::query()->distinct()->count('creator_portal_id') : 0,
            'api' => $this->isAdmin() ? QrCode::where('source', 'api')->count() : 0,
        ];
    }

    /** Beranda: panduan singkat, pembuatan cepat dengan pratinjau, ringkasan, dan QR terbaru. */
    public function home()
    {
        return view('qr.home', [
            'stats' => $this->stats(),
            'recent' => QrCode::visibleTo(portal_user())->latest('id')->limit(8)->get(),
            'isAdmin' => $this->isAdmin(),
            'daily' => $this->dailyCounts(14),
            'topCreators' => $this->isAdmin()
                ? QrCode::query()->selectRaw('MAX(creator_name) as name, COUNT(*) as total')->groupBy('creator_portal_id')->orderByDesc('total')->limit(5)->get()
                : collect(),
        ]);
    }

    /** Jumlah QR dibuat per hari selama $days hari terakhir (hari tanpa QR tetap muncul dengan nilai 0). @return array<string, int> */
    private function dailyCounts(int $days): array
    {
        $counts = QrCode::visibleTo(portal_user())->where('created_at', '>=', today()->subDays($days - 1))
            ->get(['created_at'])->countBy(fn ($qr) => $qr->created_at->format('Y-m-d'));

        return collect(range($days - 1, 0))->mapWithKeys(fn ($i) => [today()->subDays($i)->format('Y-m-d') => (int) $counts->get(today()->subDays($i)->format('Y-m-d'), 0)])->all();
    }

    /** Daftar QR yang boleh dilihat, disaring menurut ?q dan (khusus admin) ?creator. Dipakai daftar dan ekspor. */
    private function filteredQuery(Request $request): Builder
    {
        $query = QrCode::visibleTo(portal_user())->latest('id');

        if (filled($search = trim((string) $request->query('q')))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)
                ->orWhere('target_url', 'like', $like)
                ->orWhere('creator_name', 'like', $like)
                ->orWhere('creator_nik', 'like', $like));
        }

        // Hanya administrator yang boleh menyaring menurut pembuat.
        $creatorFilter = $this->isAdmin() ? (string) $request->query('creator', '') : '';
        if ($creatorFilter !== '') {
            $query->where('creator_portal_id', $creatorFilter);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $perPage = in_array((int) $request->query('per_page'), [15, 25, 50, 100], true) ? (int) $request->query('per_page') : 15;
        $search = trim((string) $request->query('q'));
        $creatorFilter = $this->isAdmin() ? (string) $request->query('creator', '') : '';
        $query = $this->filteredQuery($request);

        $creators = $this->isAdmin()
            ? QrCode::query()->selectRaw('creator_portal_id, MAX(creator_name) as creator_name, COUNT(*) as total')
                ->groupBy('creator_portal_id')->orderBy('creator_name')->get()
            : collect();

        return view('qr.index', [
            'items' => $query->paginate($perPage)->withQueryString(),
            'stats' => $this->stats(),
            'creators' => $creators,
            'filters' => ['q' => $search, 'creator' => $creatorFilter, 'per_page' => $perPage],
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    /** Ekspor daftar (sesuai saringan yang sedang dipakai) ke CSV yang bisa dibuka di Excel. */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['UUID', 'Judul', 'Alamat tujuan', 'Keterangan', 'Pembuat', 'NIK', 'Level', 'Sumber', 'Dibuat']);
            foreach ($query->lazyById(500) as $qr) {
                fputcsv($out, array_map([self::class, 'csvSafe'], [
                    $qr->uuid, $qr->title, $qr->target_url, $qr->description, $qr->creator_name, $qr->creator_nik,
                    $qr->creatorRoleLabel(), $qr->source, $qr->created_at?->format('Y-m-d H:i:s'),
                ]));
            }
            fclose($out);
        }, 'qr-akp-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Mencegah injeksi rumus spreadsheet: sel yang diawali = + - @ diberi tanda kutip di depan. */
    private static function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }

    public function create()
    {
        return view('qr.create');
    }

    /** Pratinjau QR sebelum disimpan (tidak menyimpan apa pun). */
    public function preview(Request $request): JsonResponse
    {
        $request->validate(['target_url' => ['required', 'string', new PublicWebUrl()]]);

        $draft = new QrCode(['target_url' => trim($request->input('target_url')), 'title' => 'pratinjau']);
        [$png] = QrImage::render($draft, 'png', 480);

        return response()->json(['image' => 'data:image/png;base64,'.base64_encode($png)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'target_url' => ['required', 'string', new PublicWebUrl()],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'title.required' => 'Judul QR wajib diisi.',
            'target_url.required' => 'Alamat tujuan wajib diisi.',
        ]);

        $user = portal_user();

        $qr = QrCode::create([
            'title' => trim($data['title']),
            'target_url' => trim($data['target_url']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'creator_portal_id' => (string) $user->id,
            'creator_name' => $user->name,
            'creator_nik' => $user->employee_no,
            'creator_email' => $user->email,
            'creator_role' => $this->isAdmin() ? 'admin' : 'user',
            'source' => 'web',
        ]);

        return redirect()->route('qr.show', $qr)->with('success', 'QR berhasil dibuat. Unduh dan cetak dari halaman ini.');
    }

    public function show(string $qr)
    {
        return view('qr.show', ['qr' => $this->findVisible($qr), 'isAdmin' => $this->isAdmin()]);
    }

    public function edit(string $qr)
    {
        return view('qr.edit', ['qr' => $this->findVisible($qr)]);
    }

    /** Hanya judul dan keterangan yang boleh diubah. Alamat tujuan terkunci karena QR yang sudah dicetak tidak bisa ikut berubah. */
    public function update(Request $request, string $qr)
    {
        $item = $this->findVisible($qr);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ], ['title.required' => 'Judul QR wajib diisi.']);

        $item->update([
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'updated_by_name' => portal_user()->name,
        ]);

        return redirect()->route('qr.show', $item)->with('success', 'Data QR diperbarui.');
    }

    public function destroy(string $qr)
    {
        $item = $this->findVisible($qr);
        // external_ref dikosongkan supaya sistem pemanggil bisa membuat QR baru dengan referensi yang sama.
        $item->update(['deleted_by_name' => portal_user()->name, 'external_ref' => null]);
        $item->delete();

        return redirect()->route('qr.index')->with('success', 'QR dihapus.');
    }

    /** Gambar QR (png/svg). ?unduh=1 memicu unduhan berkas. */
    public function image(Request $request, string $qr, string $format): Response
    {
        abort_unless(isset(QrImage::FORMATS[$format]), 404);

        $item = $this->findVisible($qr);
        $preset = (string) $request->query('warna', 'akp');
        $style = [
            'fg' => QrImage::PRESETS[$preset][1] ?? null,
            'logo' => $request->query('logo', '1') !== '0',
        ];
        [$content, $mime] = QrImage::render($item, $format, $format === 'png' ? 900 : 600, $style);

        $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff'];
        if ($request->boolean('unduh')) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$item->downloadName($format).'"';
        }

        return response($content, 200, $headers);
    }
}
