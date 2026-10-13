<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

/**
 * Authorization for campaigns (docs/project.md §5.4).
 */
class CampaignPolicy
{
    /**
     * Whether the user may list campaigns.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('campaigns.view');
    }

    /**
     * Whether the user may view this campaign.
     */
    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.view') && $this->sameOrganization($user, $campaign);
    }

    /**
     * Whether the user may create campaigns.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('campaigns.create');
    }

    /**
     * Whether the user may edit this campaign, while it is a draft.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.create')
            && $campaign->status === 'draft'
            && $this->sameOrganization($user, $campaign);
    }

    /**
     * Whether the user may delete campaigns at all.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('campaigns.create');
    }

    /**
     * Whether the user may delete this campaign, at any status.
     */
    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.create')
            && $this->sameOrganization($user, $campaign);
    }

    /**
     * A campaign in the bin can be restored by whoever could author it.
     */
    public function restore(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.create') && $this->sameOrganization($user, $campaign);
    }

    /**
     * Whether the user may permanently delete this campaign.
     */
    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return $this->restore($user, $campaign);
    }

    /**
     * Whether this user may send this campaign at all — first time or again.
     */
    public function send(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.send')
            && $this->sameOrganization($user, $campaign);
    }

    /**
     * Whether the campaign belongs to the user's organization.
     */
    private function sameOrganization(User $user, Campaign $campaign): bool
    {
        return $user->organization_id !== null
            && $user->organization_id === $campaign->organization_id;
    }
}
