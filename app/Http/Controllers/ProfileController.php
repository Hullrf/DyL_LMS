<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\AvisosPorCorreoService;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Guarda qué categorías de avisos quiere recibir por correo (las no marcadas se apagan).
     */
    public function actualizarAvisosCorreo(Request $request): RedirectResponse
    {
        $claves = array_keys(AvisosPorCorreoService::CATEGORIAS);

        $validado = $request->validate([
            'avisos'   => ['nullable', 'array'],
            'avisos.*' => [Rule::in($claves)],
        ]);

        $desactivados = array_values(array_diff($claves, $validado['avisos'] ?? []));

        $request->user()->forceFill(['correo_desactivados' => $desactivados ?: null])->save();

        return Redirect::route('profile.edit')->with('status', 'avisos-correo-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
