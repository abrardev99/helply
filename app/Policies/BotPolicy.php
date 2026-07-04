<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Bot;
use App\Models\User;

class BotPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Team membership is already enforced by the EnsureTeamMembership middleware.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Bot $bot): bool
    {
        return $user->belongsToTeam($bot->team);
    }

    /**
     * Determine whether the user can create models in their current team.
     */
    public function create(User $user): bool
    {
        return $user->currentTeam !== null
            && $user->hasTeamPermission($user->currentTeam, TeamPermission::ManageBots);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Bot $bot): bool
    {
        return $user->belongsToTeam($bot->team)
            && $user->hasTeamPermission($bot->team, TeamPermission::ManageBots);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Bot $bot): bool
    {
        return $this->update($user, $bot);
    }
}
