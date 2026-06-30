<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            AreaSeeder::class,
        ]);
    }

    private function authenticateAdministrator(): User
    {
        $admin = User::factory()->create([
            'name' => 'Administrador de Prueba',
            'username' => 'admin.employees',
            'email' => 'admin.employees@uyn.test',
            'status' => 'active',
        ]);

        $admin->assignRole('Administrador');

        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function getArea(string $name): Area
    {
        return Area::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    public function test_administrator_can_list_production_areas(): void
    {
        $this->authenticateAdministrator();

        $this->getJson('/api/v1/areas')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.name', 'Corte')
            ->assertJsonPath('data.1.name', 'Diseño')
            ->assertJsonPath('data.2.name', 'Bordado')
            ->assertJsonPath('data.3.name', 'Maquila')
            ->assertJsonPath('data.4.name', 'Preparación')
            ->assertJsonPath('data.5.name', 'Terminado');
    }

    public function test_administrator_can_create_internal_employee(): void
    {
        $admin = $this->authenticateAdministrator();

        $bordadoArea = $this->getArea('Bordado');

        $response = $this->postJson('/api/v1/employees', [
            'name' => 'María Hernández',
            'area_id' => $bordadoArea->id,
            'worker_type' => 'internal',
            'phone' => '2221234567',
            'notes' => 'Responsable principal del área de bordado.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Trabajador registrado correctamente.')
            ->assertJsonPath('data.name', 'María Hernández')
            ->assertJsonPath('data.worker_type', 'internal')
            ->assertJsonPath('data.worker_type_label', 'Empleado interno')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.area.id', $bordadoArea->id)
            ->assertJsonPath('data.area.name', 'Bordado');

        $employee = Employee::query()
            ->where('name', 'María Hernández')
            ->firstOrFail();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'María Hernández',
            'area_id' => $bordadoArea->id,
            'worker_type' => 'internal',
            'phone' => '2221234567',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'employees',
            'action' => 'created',
            'subject_type' => Employee::class,
            'subject_id' => $employee->id,
        ]);
    }

    public function test_administrator_can_create_external_worker(): void
    {
        $this->authenticateAdministrator();

        $maquilaArea = $this->getArea('Maquila');

        $this->postJson('/api/v1/employees', [
            'name' => 'Taller Textil López',
            'area_id' => $maquilaArea->id,
            'worker_type' => 'external',
            'phone' => '2227654321',
            'notes' => 'Maquilero externo para confección de prendas.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.worker_type', 'external')
            ->assertJsonPath(
                'data.worker_type_label',
                'Maquilero externo'
            )
            ->assertJsonPath('data.area.name', 'Maquila');

        $this->assertDatabaseHas('employees', [
            'name' => 'Taller Textil López',
            'worker_type' => 'external',
            'status' => 'active',
        ]);
    }

    public function test_administrator_can_filter_workers_by_area_type_and_status(): void
    {
        $this->authenticateAdministrator();

        $bordadoArea = $this->getArea('Bordado');
        $maquilaArea = $this->getArea('Maquila');

        Employee::factory()->create([
            'name' => 'María Bordado',
            'area_id' => $bordadoArea->id,
            'worker_type' => 'internal',
            'status' => 'active',
            'phone' => '2221111111',
        ]);

        Employee::factory()->create([
            'name' => 'Taller Maquila',
            'area_id' => $maquilaArea->id,
            'worker_type' => 'external',
            'status' => 'active',
            'phone' => '2222222222',
        ]);

        Employee::factory()->create([
            'name' => 'Maquilero Inactivo',
            'area_id' => $maquilaArea->id,
            'worker_type' => 'external',
            'status' => 'inactive',
            'phone' => '2223333333',
        ]);

        $this->getJson(
            "/api/v1/employees?area_id={$maquilaArea->id}"
            . '&worker_type=external'
            . '&status=active'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Taller Maquila')
            ->assertJsonPath('data.0.worker_type', 'external')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.area.name', 'Maquila');
    }

    public function test_administrator_can_search_workers_by_name_or_phone(): void
    {
        $this->authenticateAdministrator();

        $corteArea = $this->getArea('Corte');

        Employee::factory()->create([
            'name' => 'José Martínez',
            'area_id' => $corteArea->id,
            'phone' => '2224445566',
        ]);

        $this->getJson('/api/v1/employees?search=Martínez')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'José Martínez');

        $this->getJson('/api/v1/employees?search=2224445566')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'José Martínez');
    }

    public function test_administrator_can_update_worker_and_register_audit_log(): void
    {
        $admin = $this->authenticateAdministrator();

        $bordadoArea = $this->getArea('Bordado');

        $employee = Employee::factory()->create([
            'name' => 'María Hernández',
            'area_id' => $bordadoArea->id,
            'worker_type' => 'internal',
            'phone' => '2221234567',
            'notes' => 'Registro original.',
            'status' => 'active',
        ]);

        $this->patchJson("/api/v1/employees/{$employee->id}", [
            'phone' => '2220001122',
            'notes' => 'Teléfono y observaciones actualizadas.',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Trabajador actualizado correctamente.'
            )
            ->assertJsonPath('data.phone', '2220001122')
            ->assertJsonPath(
                'data.notes',
                'Teléfono y observaciones actualizadas.'
            );

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'phone' => '2220001122',
            'notes' => 'Teléfono y observaciones actualizadas.',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'employees',
            'action' => 'updated',
            'subject_type' => Employee::class,
            'subject_id' => $employee->id,
        ]);
    }

    public function test_administrator_can_deactivate_and_activate_worker(): void
    {
        $admin = $this->authenticateAdministrator();

        $employee = Employee::factory()->create([
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/employees/{$employee->id}/deactivate")
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Trabajador desactivado correctamente.'
            )
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'employees',
            'action' => 'deactivated',
            'subject_type' => Employee::class,
            'subject_id' => $employee->id,
        ]);

        $this->postJson("/api/v1/employees/{$employee->id}/activate")
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Trabajador activado correctamente.'
            )
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'employees',
            'action' => 'activated',
            'subject_type' => Employee::class,
            'subject_id' => $employee->id,
        ]);
    }

    public function test_user_with_consultation_role_can_view_workers_but_cannot_create_them(): void
    {
        $user = User::factory()->create([
            'username' => 'supervisor.prueba',
            'email' => 'supervisor@uyn.test',
            'status' => 'active',
        ]);

        $user->assignRole('Usuario de consulta/supervisión');

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/employees')
            ->assertOk();

        $bordadoArea = $this->getArea('Bordado');

        $this->postJson('/api/v1/employees', [
            'name' => 'Trabajador no autorizado',
            'area_id' => $bordadoArea->id,
            'worker_type' => 'internal',
            'phone' => '2229999999',
        ])
            ->assertForbidden();
    }

    public function test_user_without_employee_permissions_cannot_access_worker_catalog(): void
    {
        $user = User::factory()->create([
            'username' => 'sin.permisos',
            'email' => 'sin.permisos@uyn.test',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/areas')
            ->assertForbidden();

        $this->getJson('/api/v1/employees')
            ->assertForbidden();
    }

    public function test_worker_creation_requires_valid_data(): void
    {
        $this->authenticateAdministrator();

        $this->postJson('/api/v1/employees', [
            'name' => '',
            'area_id' => 999999,
            'worker_type' => 'otro',
            'phone' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'area_id',
                'worker_type',
                'phone',
            ]);

        $this->assertDatabaseCount('employees', 0);
    }
}