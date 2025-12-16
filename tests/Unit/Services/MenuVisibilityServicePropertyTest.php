<?php

namespace Tests\Unit\Services;

use App\Services\MenuVisibilityService;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for MenuVisibilityService
 * 
 * Feature: point-of-sale
 */
class MenuVisibilityServicePropertyTest extends TestCase
{
    use TestTrait;

    private MenuVisibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MenuVisibilityService();
    }

    /**
     * Feature: point-of-sale, Property 19: Permission-Based Menu Visibility
     * Validates: Requirements 10.3
     * 
     * For any user with a specific role, the visible menu items SHALL only include
     * items authorized by that role's permissions.
     */
    #[Test]
    public function visible_menu_items_only_include_authorized_items(): void
    {
        $allPermissions = MenuVisibilityService::getAllPosPermissions();
        
        $this
            ->limitTo(100)
            ->forAll(
                // Generate a random subset of permissions
                Generators::bind(
                    Generators::choose(0, count($allPermissions)),
                    function (int $count) use ($allPermissions) {
                        return Generators::constant(
                            $this->getRandomPermissionSubset($allPermissions, $count)
                        );
                    }
                )
            )
            ->then(function (array $userPermissions) {
                $visibleItems = $this->service->getVisibleMenuItems($userPermissions);

                foreach ($visibleItems as $menuKey) {
                    $menuConfig = MenuVisibilityService::MENU_ITEMS[$menuKey] ?? null;
                    
                    // Menu item must exist in configuration
                    $this->assertNotNull($menuConfig, "Menu item '$menuKey' must exist in configuration");
                    
                    $requiredPermission = $menuConfig['permission'];
                    
                    if ($requiredPermission !== null) {
                        // If permission is required, user must have it
                        $this->assertContains(
                            $requiredPermission,
                            $userPermissions,
                            "Visible menu item '$menuKey' requires permission '$requiredPermission' which user does not have"
                        );
                    }
                }
            });
    }

    /**
     * Feature: point-of-sale, Property 19: Permission-Based Menu Visibility (Inverse)
     * Validates: Requirements 10.3
     * 
     * For any user with specific permissions, all menu items requiring those permissions
     * SHALL be visible.
     */
    #[Test]
    public function all_authorized_menu_items_are_visible(): void
    {
        $allPermissions = MenuVisibilityService::getAllPosPermissions();
        
        $this
            ->limitTo(100)
            ->forAll(
                Generators::bind(
                    Generators::choose(0, count($allPermissions)),
                    function (int $count) use ($allPermissions) {
                        return Generators::constant(
                            $this->getRandomPermissionSubset($allPermissions, $count)
                        );
                    }
                )
            )
            ->then(function (array $userPermissions) {
                $visibleItems = $this->service->getVisibleMenuItems($userPermissions);

                // Check that all menu items the user should have access to are visible
                foreach (MenuVisibilityService::MENU_ITEMS as $menuKey => $menuConfig) {
                    $requiredPermission = $menuConfig['permission'];
                    
                    if ($requiredPermission === null) {
                        // Items without permission requirements should always be visible
                        $this->assertContains(
                            $menuKey,
                            $visibleItems,
                            "Menu item '$menuKey' with no permission requirement should be visible"
                        );
                    } elseif (in_array($requiredPermission, $userPermissions, true)) {
                        // Items with matching permissions should be visible
                        $this->assertContains(
                            $menuKey,
                            $visibleItems,
                            "Menu item '$menuKey' should be visible when user has permission '$requiredPermission'"
                        );
                    } else {
                        // Items without matching permissions should NOT be visible
                        $this->assertNotContains(
                            $menuKey,
                            $visibleItems,
                            "Menu item '$menuKey' should NOT be visible when user lacks permission '$requiredPermission'"
                        );
                    }
                }
            });
    }

    /**
     * Feature: point-of-sale, Property 19: Permission-Based Menu Visibility (Empty Permissions)
     * Validates: Requirements 10.3
     * 
     * For any user with no permissions, only menu items without permission requirements
     * SHALL be visible.
     */
    #[Test]
    public function empty_permissions_only_show_unrestricted_items(): void
    {
        $visibleItems = $this->service->getVisibleMenuItems([]);
        
        // Count expected unrestricted items
        $expectedUnrestrictedItems = array_filter(
            MenuVisibilityService::MENU_ITEMS,
            fn($config) => $config['permission'] === null
        );
        
        $this->assertCount(
            count($expectedUnrestrictedItems),
            $visibleItems,
            'Only unrestricted menu items should be visible with empty permissions'
        );
        
        foreach ($visibleItems as $menuKey) {
            $this->assertNull(
                MenuVisibilityService::MENU_ITEMS[$menuKey]['permission'],
                "Menu item '$menuKey' should not require permissions"
            );
        }
    }

    /**
     * Feature: point-of-sale, Property 19: Permission-Based Menu Visibility (Full Permissions)
     * Validates: Requirements 10.3
     * 
     * For any user with all permissions, all menu items SHALL be visible.
     */
    #[Test]
    public function full_permissions_show_all_items(): void
    {
        $allPermissions = MenuVisibilityService::getAllPosPermissions();
        $visibleItems = $this->service->getVisibleMenuItems($allPermissions);
        
        $this->assertCount(
            count(MenuVisibilityService::MENU_ITEMS),
            $visibleItems,
            'All menu items should be visible with full permissions'
        );
    }

    /**
     * Helper method to get a random subset of permissions
     */
    private function getRandomPermissionSubset(array $allPermissions, int $count): array
    {
        if ($count === 0) {
            return [];
        }
        
        if ($count >= count($allPermissions)) {
            return $allPermissions;
        }
        
        $shuffled = $allPermissions;
        shuffle($shuffled);
        
        return array_slice($shuffled, 0, $count);
    }
}
