<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function setup()
    {
        $user      = auth()->user();
        $google2fa = app('pragmarx.google2fa');

        if (!$user->two_factor_secret) {
            $user->update(['two_factor_secret' => $google2fa->generateSecretKey()]);
        }

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        $writer = new \BaconQrCode\Writer(
            new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            )
        );
        $qrSvg = base64_encode($writer->writeString($qrCodeUrl));

        return view('auth.two-factor.setup', compact('qrSvg', 'user'));
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|string|digits:6']);

        $user      = auth()->user();
        $google2fa = app('pragmarx.google2fa');

        if (!$google2fa->verifyKey($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => 'El código es incorrecto.']);
        }

        $user->update(['two_factor_enabled' => true]);

        return redirect()->route('profile.edit')
            ->with('status', '2fa-activado');
    }

    public function disable()
    {
        auth()->user()->update([
            'two_factor_enabled' => false,
            'two_factor_secret'  => null,
        ]);

        return redirect()->route('profile.edit')
            ->with('status', '2fa-desactivado');
    }

    public function verify()
    {
        return view('auth.two-factor.verify');
    }

    public function check(Request $request)
    {
        $request->validate(['code' => 'required|string|digits:6']);

        $user      = auth()->user();
        $google2fa = app('pragmarx.google2fa');

        if (!$google2fa->verifyKey($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => 'Código incorrecto. Intenta de nuevo.']);
        }

        session(['2fa_verified' => true]);

        return redirect()->intended(route('dashboard'));
    }
}
