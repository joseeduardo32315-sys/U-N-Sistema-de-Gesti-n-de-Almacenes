<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function createAdministrator(array $attributes = []): User
    {
        $admin = User::factory()->create(array_merge([
            'name' => 'Administrador de Prueba',
            'username' => 'admin.prueba',
            'email' => 'admin.prueba@uyn.test',
            'password' => Hash::make('Segura#2026!'),
            'status' => 'active',
        ], $attributes));

        $admin->assignRole('Administrador');

        return $admin;
    }

    public function test_active_user_can_login_with_username(): void
    {
        $this->createAdministrator();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'admin.prueba',
            'password' => 'Segura#2026!',
            'device_name' => 'phpunit-test',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesión correcto.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.username', 'admin.prueba')
            ->assertJsonPath('user.roles.0', 'Administrador')
            ->assertJsonStructure([
                'access_token',
                'user' => [
                    'id',
                    'name',
                    'username',
                    'email',
                    'status',
                    'roles',
                    'permissions',
                ],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->createAdministrator();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'admin.prueba',
            'password' => 'ContraseñaIncorrecta#2026!',
            'device_name' => 'phpunit-test',
        ])
            ->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'Las credenciales proporcionadas son incorrectas.'
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->createAdministrator([
            'username' => 'admin.inactivo',
            'email' => 'admin.inactivo@uyn.test',
            'status' => 'inactive',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'admin.inactivo',
            'password' => 'Segura#2026!',
            'device_name' => 'phpunit-test',
        ])
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Tu cuenta se encuentra inactiva. Contacta al administrador.'
            );
    }

    public function test_authenticated_user_can_view_own_profile(): void
    {
        $admin = $this->createAdministrator();

        $token = $admin->createToken('phpunit-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'admin.prueba')
            ->assertJsonPath('data.roles.0', 'Administrador');
    }

    public function test_authenticated_user_can_logout_and_revoke_current_token(): void
    {
        $admin = $this->createAdministrator();

        $token = $admin->createToken('phpunit-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}