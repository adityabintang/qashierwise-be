<?php

namespace App\Services;

use App\Models\PosUser;
use App\Models\Role;

/**
 * Service to determine menu visibility based on user permissions
 */
class MenuVisibilityService
{
    /**
     * All available POS menu items with their required permissions
     */
    public const MENU_ITEMS = [
        // Main section (always visible for authenticated users)
        'dashboard' => ['permission' => null, 'group' => 'main'],
        'contacts' => ['permission' => null, 'group' => 'main'],
        'messages' => ['permission' => null, 'group' => 'main'],
        'templates' => ['permission' => null, 'group' => 'main'],
        'profile' => ['permission' => null, 'group' => 'main'],
        
        // POS Section
        'pos-orders' => ['permission' => 'pos.orders.view', 'group' => 'pos'],
        'pos-payment' => ['permission' => 'pos.payments.view', 'group' => 'pos'],
        
        // Inventory Section
        'pos-products' => ['permission' => 'pos.products.view', 'group' => 'inventory'],
        'pos-categories' => ['permission' => 'pos.categories.view', 'group' => 'inventory'],
        
        // Operations Section
        'pos-stores' => ['permission' => 'pos.stores.view', 'group' => 'operations'],
        'pos-tables' => ['permission' => 'pos.tables.view', 'group' => 'operations'],
        'pos-users' => ['permission' => 'pos.users.view', 'group' => 'operations'],
        
        // Analytics Section
        'pos-reports' => ['permission' => 'pos.reports.view', 'group' => 'analytics'],
        'pos-transactions' => ['permission' => 'pos.transactions.view', 'group' => 'analytics'],
    ];

    /**
     * Get visible menu items for a given set of permissions
     *
     * @param array|null $permissions User's permissions array
     * @return array List of visible menu item keys
     */
    public function getVisibleMenuItems(?array $permissions): array
    {
        $visibleItems = [];

        foreach (self::MENU_ITEMS as $menuKey => $menuConfig) {
            if ($this->isMenuItemVisible($menuKey, $permissions)) {
                $visibleItems[] = $menuKey;
            }
        }

        return $visibleItems;
    }

    /**
     * Check if a specific menu item is visible for given permissions
     *
     * @param string $menuKey The menu item key
     * @param array|null $permissions User's permissions array
     * @return bool
     */
    public function isMenuItemVisible(string $menuKey, ?array $permissions): bool
    {
        if (!isset(self::MENU_ITEMS[$menuKey])) {
            return false;
        }

        $requiredPermission = self::MENU_ITEMS[$menuKey]['permission'];

        // Items with no required permission are always visible
        if ($requiredPermission === null) {
            return true;
        }

        // If no permissions provided, only show items without permission requirements
        if ($permissions === null || empty($permissions)) {
            return false;
        }

        // Check if user has the required permission
        return in_array($requiredPermission, $permissions, true);
    }

    /**
     * Get visible menu items for a PosUser based on their role
     *
     * @param PosUser $posUser
     * @return array List of visible menu item keys
     */
    public function getVisibleMenuItemsForUser(PosUser $posUser): array
    {
        $role = $posUser->role;
        $permissions = $role ? $role->permissions : null;

        return $this->getVisibleMenuItems($permissions);
    }

    /**
     * Get all available POS permissions
     *
     * @return array
     */
    public static function getAllPosPermissions(): array
    {
        return [
            'pos.orders.view',
            'pos.orders.create',
            'pos.orders.update',
            'pos.orders.delete',
            'pos.payments.view',
            'pos.payments.create',
            'pos.products.view',
            'pos.products.create',
            'pos.products.update',
            'pos.products.delete',
            'pos.categories.view',
            'pos.categories.create',
            'pos.categories.update',
            'pos.categories.delete',
            'pos.stores.view',
            'pos.stores.create',
            'pos.stores.update',
            'pos.stores.delete',
            'pos.tables.view',
            'pos.tables.create',
            'pos.tables.update',
            'pos.tables.delete',
            'pos.users.view',
            'pos.users.create',
            'pos.users.update',
            'pos.users.delete',
            'pos.reports.view',
            'pos.transactions.view',
        ];
    }
}
