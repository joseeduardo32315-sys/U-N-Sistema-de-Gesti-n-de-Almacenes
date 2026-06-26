<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function authenticateAdministrator(): User
    {
        $admin = User::factory()->create([
            'username' => 'admin.catalogos',
            'email' => 'admin.catalogos@uyn.test',
            'status' => 'active',
        ]);

        $admin->assignRole('Administrador');

        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    public function test_administrator_can_list_roles(): void
    {
        $this->authenticateAdministrator();

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Administrador',
            ])
            ->assertJsonFragment([
                'name' => 'Encargado de producción',
            ]);
    }

    public function test_administrator_can_list_permissions(): void
    {
        $this->authenticateAdministrator();

        $this->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'users.view',
                'module' => 'users',
                'action' => 'view',
            ])
            ->assertJsonFragment([
                'name' => 'cuts.create',
                'module' => 'cuts',
                'action' => 'create',
            ]);
    }

    public function test_user_without_roles_permission_cannot_list_roles(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/roles')
            ->assertForbidden();
    }
}