<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Agent;
use App\Models\User;

class AgentPolicy
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
    public function view(User $user, Agent $agent): bool
    {
        return $user->belongsToTeam($agent->team);
    }

    /**
     * Determine whether the user can create models in their current team.
     */
    public function create(User $user): bool
    {
        return $user->currentTeam !== null
            && $user->hasTeamPermission($user->currentTeam, TeamPermission::ManageAgents);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Agent $agent): bool
    {
        return $user->belongsToTeam($agent->team)
            && $user->hasTeamPermission($agent->team, TeamPermission::ManageAgents);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Agent $agent): bool
    {
        return $this->update($user, $agent);
    }
}
