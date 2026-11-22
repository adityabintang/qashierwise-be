<?php

namespace App\Http\Controllers\Admin\Concerns;

trait ChecksBlogPermissions
{
    /**
     * Replicate the old Filament Resource permission gate:
     * `auth()->user()->can('{action}_{entity}') || isSuperAdmin()`.
     */
    protected function authorizeBlog(string $action, string $entity): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->isSuperAdmin() || $user->can("{$action}_{$entity}")),
            403,
            'You do not have permission to perform this action.'
        );
    }
}
