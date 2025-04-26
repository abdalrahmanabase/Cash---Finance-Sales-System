<?php


namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin']);
    }

    public function view(User $user, User $model): bool
    {
        return in_array($user->role, ['super_admin', 'admin']);
    }

    public function create(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === 'super_admin';
    }

    public function delete(User $user, User $model): bool
    {
        return $user->role === 'super_admin';
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

}


