<?php

namespace App\Policies;

use App\Models\GenerationJob;
use App\Models\User;

class GenerationJobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GenerationJob $generationJob): bool
    {
        return $user->id === $generationJob->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isEmployee() && $user->isActive();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GenerationJob $generationJob): bool
    {
        return $user->id === $generationJob->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GenerationJob $generationJob): bool
    {
        return $user->isAdmin() || $user->id === $generationJob->user_id;
    }
}
