<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Create the permissions listed in config/permissions.php';

    /**
     * The role granted every permission, and handed to existing users the
     * first time this runs so nobody is locked out by the new policies.
     */
    private const ADMIN_ROLE = 'admin';

    public function handle(): int
    {
        $names = $this->permissionNames();

        if ($names === []) {
            $this->components->error('config/permissions.php defines no permissions.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($names): void {
            foreach ($names as $name) {
                $permission = Permission::findOrCreate($name, $this->guard());

                $this->components->twoColumnDetail(
                    $name,
                    $permission->wasRecentlyCreated ? '<fg=green>created</>' : '<fg=gray>exists</>'
                );
            }

            $admin = Role::findOrCreate(self::ADMIN_ROLE, $this->guard());
            $admin->syncPermissions($names);

            $this->newLine();
            $this->components->twoColumnDetail(
                'role: '.self::ADMIN_ROLE,
                count($names).' permission(s)'
            );

            $this->grantAdminOnFirstRun($admin);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->components->info('Permissions synchronised.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function permissionNames(): array
    {
        return collect(config('permissions.groups', []))
            ->collapse()
            ->keys()
            ->all();
    }

    /**
     * Nobody holds a role yet, so this is a fresh install: give existing
     * users the admin role. Once anyone has a role this never runs again.
     */
    private function grantAdminOnFirstRun(Role $admin): void
    {
        if (DB::table(config('permission.table_names.model_has_roles'))->exists()) {
            return;
        }

        $users = User::query()->get();

        if ($users->isEmpty()) {
            return;
        }

        $users->each->assignRole($admin);

        $this->newLine();
        $this->components->warn(
            'First run: granted ['.self::ADMIN_ROLE."] to {$users->count()} existing user(s)."
        );
    }

    private function guard(): string
    {
        return config('auth.defaults.guard', 'web');
    }
}
