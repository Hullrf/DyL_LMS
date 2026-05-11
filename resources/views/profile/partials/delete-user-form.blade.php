<section x-data="{ confirmar: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
    <header class="mb-6">
        <h2 class="text-lg font-semibold text-red-700">Eliminar cuenta</h2>
        <p class="mt-1 text-sm text-gray-500">
            Una vez eliminada, todos tus datos serán borrados permanentemente. Esta acción no se puede deshacer.
        </p>
    </header>

    <button
        type="button"
        x-show="!confirmar"
        x-on:click="confirmar = true"
        class="btn-danger"
    >
        Eliminar mi cuenta
    </button>

    {{-- Panel de confirmación --}}
    <div x-show="confirmar" x-cloak class="mt-4 p-5 bg-red-50 border border-red-200 rounded-xl space-y-4">
        <p class="text-sm font-medium text-red-800">
            ¿Estás seguro de que quieres eliminar tu cuenta? Ingresa tu contraseña para confirmar.
        </p>

        <form method="post" action="{{ route('profile.destroy') }}" class="space-y-4">
            @csrf
            @method('delete')

            <div>
                <label for="delete_password" class="form-label">Contraseña</label>
                <input
                    id="delete_password"
                    name="password"
                    type="password"
                    class="form-input mt-1 w-full max-w-xs"
                    placeholder="Tu contraseña actual"
                />
                @if ($errors->userDeletion->has('password'))
                    <p class="mt-1 text-sm text-red-600">{{ $errors->userDeletion->first('password') }}</p>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-danger">
                    Sí, eliminar mi cuenta
                </button>
                <button
                    type="button"
                    x-on:click="confirmar = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</section>
