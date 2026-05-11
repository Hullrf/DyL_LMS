<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Iniciar sesión</h1>
        <p class="text-gray-500 text-sm mt-1">Accede a tu cuenta para continuar aprendiendo</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if(!empty($errors) && $errors->any())
        <div class="alert alert-error mb-5" role="alert">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5" novalidate>
        @csrf

        <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-input" required autofocus autocomplete="username"
                   placeholder="correo@empresa.com"
                   aria-describedby="{{ (!empty($errors) && $errors->has('email')) ? 'email-error' : '' }}">
            @error('email')
                <p id="email-error" class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label for="password" class="form-label mb-0">Contraseña</label>
                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       class="text-xs text-dyl-blue hover:underline">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>
            <input id="password" type="password" name="password"
                   class="form-input" required autocomplete="current-password"
                   placeholder="••••••••"
                   aria-describedby="{{ (!empty($errors) && $errors->has('password')) ? 'password-error' : '' }}">
            @error('password')
                <p id="password-error" class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember"
                   class="rounded border-gray-300 text-dyl-blue focus:ring-dyl-blue">
            <label for="remember_me" class="text-sm text-gray-600 select-none cursor-pointer">
                Mantener sesión iniciada
            </label>
        </div>

        <button type="submit" class="btn-primary w-full py-2.5 text-base">
            Ingresar
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
        </button>

        @if(Route::has('register'))
            <p class="text-center text-sm text-gray-500">
                ¿No tienes cuenta?
                <a href="{{ route('register') }}" class="text-dyl-blue font-medium hover:underline">
                    Regístrate
                </a>
            </p>
        @endif
    </form>
</x-guest-layout>
