<section>
    <header class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Información del perfil</h2>
        <p class="mt-1 text-sm text-gray-500">Actualiza tu nombre, empresa y dirección de correo electrónico.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('patch')

        {{-- Nombre --}}
        <div>
            <label for="name" class="form-label">Nombre completo</label>
            <input
                id="name"
                name="name"
                type="text"
                class="form-input mt-1 w-full"
                value="{{ old('name', $user->name) }}"
                required
                autofocus
                autocomplete="name"
            />
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Empresa --}}
        <div>
            <label for="empresa" class="form-label">Empresa / Organización</label>
            <input
                id="empresa"
                name="empresa"
                type="text"
                class="form-input mt-1 w-full"
                value="{{ old('empresa', $user->empresa) }}"
                autocomplete="organization"
                placeholder="Opcional"
            />
            @error('empresa')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input
                id="email"
                name="email"
                type="email"
                class="form-input mt-1 w-full"
                value="{{ old('email', $user->email) }}"
                required
                autocomplete="username"
            />
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-sm text-yellow-800">
                        Tu dirección de correo no ha sido verificada.
                        <button form="send-verification" class="underline font-medium hover:text-yellow-900">
                            Haz clic aquí para reenviar el correo de verificación.
                        </button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-1 text-sm text-green-700 font-medium">
                            Se envió un nuevo enlace de verificación a tu correo.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="btn-primary">
                Guardar cambios
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="text-sm text-green-600 font-medium"
                >Cambios guardados.</p>
            @endif
        </div>
    </form>
</section>
