<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Runs before any user exists, so the first-run admin grant finds
        // nobody to assign and each test starts from explicit permissions.
        $this->artisan('permissions:sync');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user->fresh();
    }

    /**
     * @return array<int, string>
     */
    private function configuredPermissions(): array
    {
        return collect(config('permissions.groups'))->collapse()->keys()->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions come from configuration
    |--------------------------------------------------------------------------
    */

    public function test_sync_creates_every_permission_from_config(): void
    {
        foreach ($this->configuredPermissions() as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name]);
        }

        $this->assertSame(count($this->configuredPermissions()), Permission::count());
    }

    public function test_sync_can_run_repeatedly_without_duplicating(): void
    {
        $permissions = Permission::count();
        $roles = Role::count();

        $this->artisan('permissions:sync')->assertSuccessful();

        $this->assertSame($permissions, Permission::count());
        $this->assertSame($roles, Role::count());
    }

    public function test_sync_creates_an_admin_role_holding_every_permission(): void
    {
        $admin = Role::findByName('admin');

        $this->assertSame(
            count($this->configuredPermissions()),
            $admin->permissions()->count()
        );
    }

    public function test_a_permission_added_to_config_appears_on_the_next_sync(): void
    {
        $groups = config('permissions.groups');
        $groups['Products']['products.export'] = 'Export products';
        config(['permissions.groups' => $groups]);

        $this->artisan('permissions:sync')->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['name' => 'products.export']);
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('products.export'));
    }

    /*
    |--------------------------------------------------------------------------
    | Roles are built in the UI
    |--------------------------------------------------------------------------
    */

    public function test_a_role_can_be_created_with_permissions_from_the_ui(): void
    {
        $this->actingAs($this->userWith('roles.view', 'roles.manage'));

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'editor',
                'permissions' => [
                    Permission::findByName('products.view')->getKey(),
                    Permission::findByName('products.update')->getKey(),
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::findByName('editor');

        $this->assertEqualsCanonicalizing(
            ['products.view', 'products.update'],
            $role->permissions->pluck('name')->all()
        );
    }

    public function test_creating_a_role_requires_the_manage_permission(): void
    {
        $this->actingAs($this->userWith('roles.view'))
            ->get('/admin/roles/create')
            ->assertForbidden();

        $this->actingAs($this->userWith('roles.view', 'roles.manage'))
            ->get('/admin/roles/create')
            ->assertOk();
    }

    public function test_roles_page_requires_the_view_permission(): void
    {
        $this->actingAs($this->userWith('roles.view'))
            ->get('/admin/roles')
            ->assertOk();

        $this->actingAs($this->userWith('products.view'))
            ->get('/admin/roles')
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Assigning roles to users
    |--------------------------------------------------------------------------
    */

    public function test_users_page_requires_the_view_permission(): void
    {
        $this->actingAs($this->userWith('users.view'))
            ->get('/admin/users')
            ->assertOk();

        $this->actingAs($this->userWith('products.view'))
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_roles_can_be_assigned_and_removed_through_the_ui(): void
    {
        $manager = Role::create(['name' => 'manager']);
        $manager->givePermissionTo('products.view');

        $target = User::factory()->create();

        $this->actingAs($this->userWith('users.view', 'users.assignRoles'));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$manager->getKey()]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->hasRole('manager'));
        $this->assertTrue($target->fresh()->can('products.view'));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($target->fresh()->hasRole('manager'));
        $this->assertFalse($target->fresh()->can('products.view'));
    }

    public function test_a_role_carries_its_permissions_to_the_user(): void
    {
        $role = Role::create(['name' => 'stock-keeper']);
        $role->givePermissionTo(['products.view', 'products.update']);

        $user = User::factory()->create();
        $user->assignRole($role);
        $user = $user->fresh();

        $this->assertTrue($user->can('products.view'));
        $this->assertTrue($user->can('products.update'));
        $this->assertFalse($user->can('products.delete'));
    }

    /*
    |--------------------------------------------------------------------------
    | Product CRUD honours the policy
    |--------------------------------------------------------------------------
    */

    public function test_product_policy_maps_abilities_to_permissions(): void
    {
        $product = Product::create(['name' => 'Target', 'price' => 10, 'quantity' => 1]);

        $cases = [
            'viewAny' => 'products.view',
            'view' => 'products.view',
            'create' => 'products.create',
            'update' => 'products.update',
            'delete' => 'products.delete',
        ];

        foreach ($cases as $ability => $permission) {
            $subject = in_array($ability, ['view', 'update', 'delete'], true)
                ? $product
                : Product::class;

            $this->assertTrue(
                Gate::forUser($this->userWith($permission))->allows($ability, $subject),
                "[{$ability}] should be allowed with [{$permission}]"
            );

            $this->assertFalse(
                Gate::forUser($this->userWith())->allows($ability, $subject),
                "[{$ability}] should be denied without [{$permission}]"
            );
        }
    }

    public function test_product_pages_enforce_their_permissions(): void
    {
        $product = Product::create(['name' => 'Guarded', 'price' => 5, 'quantity' => 2]);

        // Index
        $this->actingAs($this->userWith('products.view'))->get('/admin/products')->assertOk();
        $this->actingAs($this->userWith())->get('/admin/products')->assertForbidden();

        // Create
        $this->actingAs($this->userWith('products.view', 'products.create'))
            ->get('/admin/products/create')->assertOk();
        $this->actingAs($this->userWith('products.view'))
            ->get('/admin/products/create')->assertForbidden();

        // Edit
        $this->actingAs($this->userWith('products.view', 'products.update'))
            ->get("/admin/products/{$product->getKey()}/edit")->assertOk();
        $this->actingAs($this->userWith('products.view'))
            ->get("/admin/products/{$product->getKey()}/edit")->assertForbidden();
    }

    public function test_user_policy_denies_creating_and_deleting_users(): void
    {
        $admin = $this->userWith(...$this->configuredPermissions());
        $target = User::factory()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $target));
    }
}
