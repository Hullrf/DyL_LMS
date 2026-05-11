<?php

namespace App\Policies;

use App\Models\Curso;
use App\Models\User;

class CursoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Curso $curso): bool
    {
        return $curso->estaPublicado() || $user->esAdmin() || $user->id === $curso->created_by;
    }

    public function create(User $user): bool
    {
        return $user->esAdmin() || $user->esInstructor();
    }

    public function update(User $user, Curso $curso): bool
    {
        return $user->esAdmin() || $user->id === $curso->created_by;
    }

    public function delete(User $user, Curso $curso): bool
    {
        return $user->esAdmin() || $user->id === $curso->created_by;
    }
}
