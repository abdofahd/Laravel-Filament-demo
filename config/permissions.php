<?php

/*
|--------------------------------------------------------------------------
| Application permissions
|--------------------------------------------------------------------------
|
| The catalogue of permissions this application understands, grouped for
| readability. `php artisan permissions:sync` creates exactly these rows in
| Spatie's permissions table.
|
| Roles are NOT defined here -- they are created from the Filament UI and
| pick their permissions from this list.
|
| Note: config/permission.php (singular) belongs to spatie/laravel-permission
| and configures the package itself. This file is ours.
|
*/

return [

    'groups' => [

        'Products' => [
            'products.view' => 'View products',
            'products.create' => 'Create products',
            'products.update' => 'Update products',
            'products.delete' => 'Delete products',
        ],

        'Roles' => [
            'roles.view' => 'View roles',
            'roles.manage' => 'Create, edit and delete roles',
        ],

        'Users' => [
            'users.view' => 'View users',
            'users.assignRoles' => 'Assign roles to users',
        ],

    ],

];
