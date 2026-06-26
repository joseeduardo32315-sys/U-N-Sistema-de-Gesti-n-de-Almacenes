<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
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
            'name' => 'Administrador de Prueba',
            'username' => 'admin.prueba',
            'email' => 'admin.prueba@uyn.test',
            'status' => 'active',
        ]);

        $admin->assignRole('Administrador');

        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    public function test_administrator_can_create_user_and_assign_role(): void
    {
        $admin = $this->authenticateAdministrator();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'María López',
            'username' => 'maria.lopez',
            'email' => 'maria.lopez@uyn.test',
            'password' => 'Segura#2026!',
            'password_confirmation' => 'Segura#2026!',
            'role' => 'Encargado de bordado',
            'status' => 'active',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Usuario creado correctamente.')
            ->assertJsonPath('data.username', 'maria.lopez')
            ->assertJsonPath('data.roles.0', 'Encargado de bordado');

        $user = User::where('username', 'maria.lopez')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'María López',
            'email' => 'maria.lopez@uyn.test',
            'status' => 'active',
        ]);

        $this->assertTrue(
            $user->hasRole('Encargado de bordado')
        );

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'users',
            'action' => 'created',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_administrator_can_list_users_with_filters(): void
    {
        $this->authenticateAdministrator();

        $user = User::factory()->create([
            'name' => 'María López',
            'username' => 'maria.lopez',
            'email' => 'maria.lopez@uyn.test',
            'status' => 'active',
        ]);

        $user->assignRole('Encargado de bordado');

        $this->getJson(
            '/api/v1/users?search=maria&status=active&per_page=10'
        )
            ->assertOk()
            ->assertJsonPath('data.0.username', 'maria.lopez')
            ->assertJsonPath('data.0.roles.0', 'Encargado de bordado');
    }

    public function test_user_without_permission_cannot_list_users(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $admin = $this->authenticateAdministrator();

        $this->postJson("/api/v1/users/{$admin->id}/deactivate")
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'status' => 'active',
        ]);
    }

    public function test_last_active_administrator_cannot_change_to_another_role(): void
    {
        $admin = $this->authenticateAdministrator();

        $this->putJson("/api/v1/users/{$admin->id}", [
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => 'Usuario de consulta/supervisión',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertTrue(
            $admin->fresh()->hasRole('Administrador')
        );
    }

    public function test_administrator_can_consult_operation_logs(): void
    {
        $this->authenticateAdministrator();

        $this->postJson('/api/v1/users', [
            'name' => 'María López',
            'username' => 'maria.lopez',
            'email' => 'maria.lopez@uyn.test',
            'password' => 'Segura#2026!',
            'password_confirmation' => 'Segura#2026!',
            'role' => 'Encargado de bordado',
            'status' => 'active',
        ])->assertCreated();

        $this->getJson(
            '/api/v1/operation-logs?module=users&action=created'
        )
            ->assertOk()
            ->assertJsonPath('data.0.module', 'users')
            ->assertJsonPath('data.0.action', 'created');
    }
}