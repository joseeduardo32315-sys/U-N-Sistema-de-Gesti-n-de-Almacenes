<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Limpia la caché de permisos para evitar que Spatie conserve
         * configuraciones anteriores durante el seeding.
         */
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'dashboard.view',

            // Usuarios
            'users.view',
            'users.create',
            'users.update',
            'users.deactivate',
            'users.activate',

            // Roles y permisos
            'roles.view',
            'roles.manage',

            // Bitácora
            'operation-logs.view',

            // Empleados y maquileros
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.deactivate',
            'employees.activate',

            // Modelos de prendas
            'garment-models.view',
            'garment-models.create',
            'garment-models.update',
            'garment-models.deactivate',
            'garment-models.activate',

            // Cortes
            'cuts.view',
            'cuts.create',
            'cuts.update',
            'cuts.cancel',
            'cuts.finish',

            // Procesos de producción
            'processes.view',
            'processes.assign',
            'processes.update-status',

            // Entregas y recepciones
            'deliveries.create',
            'receptions.create',

            // Incidencias
            'incidents.view',
            'incidents.create',
            'incidents.update',
            'incidents.close',

            // Reportes
            'reports.view',
            'reports.export',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'Administrador' => $permissions,

            'Encargado de producción' => [
                'dashboard.view',

                'employees.view',
                'employees.create',
                'employees.update',
                'employees.deactivate',
                'employees.activate',

                'garment-models.view',

                'cuts.view',
                'cuts.create',
                'cuts.update',
                'cuts.cancel',
                'cuts.finish',

                'processes.view',
                'processes.assign',
                'processes.update-status',

                'deliveries.create',
                'receptions.create',

                'incidents.view',
                'incidents.create',
                'incidents.update',
                'incidents.close',

                'reports.view',
                'reports.export',
            ],

            'Encargado de corte' => [
                'dashboard.view',
                'garment-models.view',

                'cuts.view',
                'cuts.create',
                'cuts.update',

                'processes.view',
                'processes.update-status',

                'incidents.view',
                'incidents.create',
            ],

            'Encargado de diseño' => [
                'dashboard.view',
                'garment-models.view',

                'cuts.view',

                'processes.view',
                'processes.update-status',

                'incidents.view',
                'incidents.create',
            ],

            'Encargado de bordado' => [
                'dashboard.view',
                'garment-models.view',

                'cuts.view',

                'processes.view',
                'processes.update-status',

                'deliveries.create',
                'receptions.create',

                'incidents.view',
                'incidents.create',
            ],

            'Encargado de maquila' => [
                'dashboard.view',
                'garment-models.view',

                'cuts.view',

                'processes.view',
                'processes.update-status',

                'deliveries.create',
                'receptions.create',

                'incidents.view',
                'incidents.create',
            ],

            'Encargado de preparación/terminado' => [
                'dashboard.view',
                'garment-models.view',

                'cuts.view',
                'cuts.finish',

                'processes.view',
                'processes.update-status',

                'deliveries.create',
                'receptions.create',

                'incidents.view',
                'incidents.create',
                'incidents.update',
            ],

            'Usuario de consulta/supervisión' => [
                'dashboard.view',
                'employees.view',
                'garment-models.view',
                'cuts.view',
                'processes.view',
                'incidents.view',
                'reports.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');

            /*
             * syncPermissions mantiene el seeder reutilizable:
             * agrega, actualiza o elimina permisos según esta definición.
             */
            $role->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}