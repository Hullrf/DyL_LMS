<section>
    <header class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Cambiar contraseña</h2>
        <p class="mt-1 text-sm text-gray-500">Usa una contraseña larga y aleatoria para mantener tu cuenta segura.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="form-label">Contraseña actual</label>
            <input
                id="update_password_current_password"
                name="current_password"
                type="password"
                class="form-input mt-1 w-full"
                autocomplete="current-password"
            />
            @if ($errors->updatePassword->has('current_password'))
                <p class="mt-1 text-sm text-red-600">{{ $errors->updatePassword->first('current_password') }}</p>
            @endif
        </div>

        <div>
            <label for="update_password_password" class="form-label">Nueva contraseña</label>
            <input
                id="update_password_password"
                name="password"
                type="password"
                class="form-input mt-1 w-full"
                autocomplete="new-password"
            />
            @if ($errors->updatePassword->has('password'))
                <p class="mt-1 text-sm text-red-600">{{ $errors->updatePassword->first('password') }}</p>
            @endif
        </div>

        <div>
            <label for="update_password_password_confirmation" class="form-label">Confirmar nueva contraseña</label>
            <input
                id="update_password_password_confirmation"
                name="password_confirmation"
                type="password"
                class="form-input mt-1 w-full"
                autocomplete="new-password"
            />
            @if ($errors->updatePassword->has('password_confirmation'))
                <p class="mt-1 text-sm text-red-600">{{ $errors->updatePassword->first('password_confirmation') }}</p>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="btn-primary">
                Actualizar contraseña
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="text-sm text-green-600 font-medium"
                >Contraseña actualizada.</p>
            @endif
        </div>
    </form>
</section>
