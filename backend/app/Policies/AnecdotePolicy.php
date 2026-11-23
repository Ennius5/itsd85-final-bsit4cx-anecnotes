<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Anecdote;
use App\Models\User;

/**
 * Who may do what with anecdotes. Checked on the server for every request;
 * the app only hides buttons for convenience.
 */
class AnecdotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Admin and guidance: all. Teachers: their own, and those about students in sections they advise. */
    public function view(User $user, Anecdote $anecdote): bool
    {
        if ($user->hasRole(Role::Admin, Role::Guidance) || $anecdote->author_id === $user->id) {
            return true;
        }

        return $anecdote->section !== null && $anecdote->section->adviser_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Authors edit their own open anecdotes; guidance and admin may override the AI category and severity. */
    public function update(User $user, Anecdote $anecdote): bool
    {
        if ($user->hasRole(Role::Admin, Role::Guidance)) {
            return true;
        }

        return $anecdote->author_id === $user->id && $anecdote->isOpen();
    }

    /** Only the author may change the text itself, and only while it is open. */
    public function editText(User $user, Anecdote $anecdote): bool
    {
        return $anecdote->author_id === $user->id && $anecdote->isOpen();
    }

    public function delete(User $user, Anecdote $anecdote): bool
    {
        return $user->isAdmin() || ($anecdote->author_id === $user->id && $anecdote->isOpen());
    }

    public function resolve(User $user, Anecdote $anecdote): bool
    {
        return $user->isGuidance() && $anecdote->isOpen();
    }
}
