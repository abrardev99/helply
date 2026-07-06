<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    /**
     * Team membership is already enforced by the EnsureTeamMembership middleware.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Agent $agent): bool
    {
        return $user->belongsToTeam($agent->team);
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null
            && $user->hasTeamPermission($user->currentTeam, TeamPermission::ManageAgents);
    }

    public function update(User $user, Agent $agent): bool
    {
        return $user->belongsToTeam($agent->team)
            && $user->hasTeamPermission($agent->team, TeamPermission::ManageAgents);
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $this->update($user, $agent);
    }
}
