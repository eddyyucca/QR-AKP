<?php

namespace Akp\PortalSso\Http\Controllers;

use Akp\PortalSso\Events\PortalUserLoggedIn;
use Akp\PortalSso\Events\PortalUserLoggingOut;
use Akp\PortalSso\Exceptions\InvalidSsoToken;
use Akp\PortalSso\Exceptions\LoginDenied;
use Akp\PortalSso\PortalSession;
use Akp\PortalSso\PortalUser;
use Akp\PortalSso\RoleManifest;
use Akp\PortalSso\SsoTokenVerifier;
use Akp\PortalSso\Support\PortalAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class SsoController extends Controller
{
    public function __construct(private SsoTokenVerifier $verifier, private PortalSession $portal)
    {
    }

    /** Tujuan redirect dari Portal: tukar token SSO dengan sesi aplikasi. */
    public function callback(Request $request): RedirectResponse|Response
    {
        try {
            $claims = $this->verifier->verify((string) $request->query('token'));
        } catch (InvalidSsoToken $e) {
            return response()->view('portal-sso::sso-error', [
                'message' => $e->getMessage(),
                'portalUrl' => portal_sso_url(),
            ], 403);
        }

        $this->portal->start($claims);
        PortalAddress::remember($claims['portal'] ?? null);   // alamat Portal yang dipakai pengguna ini (IP atau domain)

        // Titik sambung untuk aplikasi dengan akun lokal: listener memetakan pengguna Portal ke user lokal (dan boleh menolak).
        try {
            event(new PortalUserLoggedIn(PortalUser::fromClaims($claims), $request));
        } catch (LoginDenied $e) {
            $this->portal->end();

            return response()->view('portal-sso::sso-error', [
                'message' => $e->getMessage(),
                'portalUrl' => portal_sso_url(),
            ], 403);
        }

        // Alamat tujuan disimpan oleh aplikasi ini sendiri (bukan dari parameter URL), jadi bukan open-redirect.
        return redirect()->to($request->session()->pull('portal.intended') ?: config('sso.home'));
    }

    /** Keluar dari aplikasi lalu kembali ke Portal (sesi Portal tetap berjalan agar bisa pindah aplikasi). */
    public function logout(): RedirectResponse
    {
        $portalUrl = portal_sso_url();   // dibaca sebelum sesi dihapus

        event(new PortalUserLoggingOut($this->portal->user()));
        $this->portal->end();

        return redirect()->away($portalUrl);
    }

    /**
     * Untuk "Tes koneksi" di Portal. Tidak membuka rahasia apa pun.
     * Jika ada ?challenge=..., dijawab dengan HMAC-nya memakai rahasia SSO, sehingga Portal bisa memastikan
     * kedua sisi memakai rahasia yang sama tanpa rahasia itu dikirim.
     */
    public function health(Request $request): JsonResponse
    {
        $secret = (string) config('sso.secret');
        $challenge = $request->query('challenge');

        return response()->json([
            'package' => 'akp/portal-sso',
            'app' => config('sso.app_code'),
            'configured' => $secret !== '' && filled(config('sso.app_code')),
            'proof' => is_string($challenge) && $challenge !== '' && strlen($challenge) <= 128 && $secret !== ''
                ? hash_hmac('sha256', $challenge, $secret)
                : null,
        ]);
    }

    /**
     * Daftar level yang disediakan aplikasi ini (config/sso.php > roles), dibaca Portal untuk mengisi pilihan level otomatis.
     * Bukti HMAC atas ?challenge= sama seperti /sso/health, supaya Portal yakin yang menjawab adalah aplikasi yang benar.
     */
    public function manifest(Request $request): JsonResponse
    {
        $secret = (string) config('sso.secret');
        $challenge = $request->query('challenge');

        return response()->json([
            'package' => 'akp/portal-sso',
            'app' => config('sso.app_code'),
            'roles' => RoleManifest::fromConfig(),
            'proof' => is_string($challenge) && $challenge !== '' && strlen($challenge) <= 128 && $secret !== ''
                ? hash_hmac('sha256', $challenge, $secret)
                : null,
        ]);
    }
}
