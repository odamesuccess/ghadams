<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\AssetSuggestion;
use App\Models\User;

/**
 * Who may run AI research and review its suggestions.
 * Same scoping as asset deletion: Super Admin, or a user in the asset's unit.
 */
class AssetSuggestionPolicy
{
    /** View the suggestions list for an asset. */
    public function viewAny(User $user, Asset $asset): bool
    {
        return ($user->can('assets.research') || $user->can('assets.review-suggestions'))
            && $this->canAccessAsset($user, $asset);
    }

    /** Start AI research for an asset. */
    public function create(User $user, Asset $asset): bool
    {
        return $user->can('assets.research') && $this->canAccessAsset($user, $asset);
    }

    public function approve(User $user, AssetSuggestion $suggestion): bool
    {
        return $user->can('assets.review-suggestions')
            && $this->canAccessAsset($user, $suggestion->asset);
    }

    public function reject(User $user, AssetSuggestion $suggestion): bool
    {
        return $this->approve($user, $suggestion);
    }

    protected function canAccessAsset(User $user, ?Asset $asset): bool
    {
        if (! $asset) {
            return false;
        }

        return $user->hasRole('Super Admin') || (int) $asset->unit_id === (int) $user->unit_id;
    }
}
