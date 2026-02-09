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
        'pos-orders' => ['permission' => 'pos.view_orders', 'group' => 'pos'],
        'pos-payment' => ['permission' => 'pos.process_payment', 'group' => 'pos'],

        // Inventory Section
        'pos-products' => ['permission' => 'pos.view_products', 'group' => 'inventory'],
        'pos-categories' => ['permission' => 'pos.view_categories', 'group' => 'inventory'],

        // Operations Section
        'pos-stores' => ['permission' => 'pos.view_stores', 'group' => 'operations'],
        'pos-tables' => ['permission' => 'pos.view_tables', 'group' => 'operations'],
        'pos-users' => ['permission' => 'pos.manage_users', 'group' => 'operations'],

        // Analytics Section
        'pos-reports' => ['permission' => 'pos.view_reports', 'group' => 'analytics'],
        'pos-transactions' => ['permission' => 'pos.view_orders', 'group' => 'analytics'],
    ];

    /**
     * Get visible menu items for a given set of permissions
     *
     * @param  array|null  $permissions  User's permissions array
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
     * @param  string  $menuKey  The menu item key
     * @param  array|null  $permissions  User's permissions array
     */
    public function isMenuItemVisible(string $menuKey, ?array $permissions): bool
    {
        if (! isset(self::MENU_ITEMS[$menuKey])) {
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
     * @return array List of visible menu item keys
     */
    public function getVisibleMenuItemsForUser(PosUser $posUser): array
    {
        $user = $posUser->user;
        $permissions = $user ? $user->getAllPermissions()->pluck('name')->toArray() : null;

        return $this->getVisibleMenuItems($permissions);
    }

    /**
     * Get all available POS permissions
     */
    public static function getAllPosPermissions(): array
    {
        return [
            'pos.view_orders',
            'pos.create_order',
            'pos.edit_order',
            'pos.manage_orders',
            'pos.void_order',
            'pos.process_payment',
            'pos.view_products',
            'pos.edit_products',
            'pos.manage_products',
            'pos.delete_products',
            'pos.view_categories',
            'pos.manage_categories',
            'pos.view_stores',
            'pos.manage_stores',
            'pos.view_tables',
            'pos.manage_tables',
            'pos.manage_users',
            'pos.view_users',
            'pos.view_reports',
            'pos.export_reports',
            'pos.view_inventory',
            'pos.adjust_inventory',
            'pos.give_discount',
            'pos.refund_payment',
        ];
    }
}
