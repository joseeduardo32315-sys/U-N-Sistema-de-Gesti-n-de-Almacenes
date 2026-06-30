<?php

namespace Tests\Feature;

use App\Models\GarmentModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GarmentModelApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(RolePermissionSeeder::class);
    }

    private function authenticateAdministrator(): User
    {
        $admin = User::factory()->create([
            'name' => 'Administrador de Prueba',
            'username' => 'admin.models',
            'email' => 'admin.models@uyn.test',
            'status' => 'active',
        ]);

        $admin->assignRole('Administrador');

        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function createGarmentModel(array $attributes = []): GarmentModel
    {
        return GarmentModel::factory()->create(array_merge([
            'code' => 'PM-23',
            'name' => 'Conjunto infantil bordado',
            'description' => 'Modelo de prueba.',
            'size_range' => '2 a 8',
            'status' => 'active',
        ], $attributes));
    }

    public function test_administrator_can_create_garment_model_without_image(): void
    {
        $admin = $this->authenticateAdministrator();

        $response = $this->postJson('/api/v1/garment-models', [
            'code' => 'pm-23',
            'name' => 'Conjunto infantil bordado',
            'description' => 'Conjunto compuesto por playera y short.',
            'size_range' => '2 a 8',
            'status' => 'active',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Modelo de prenda registrado correctamente.'
            )
            ->assertJsonPath('data.code', 'PM-23')
            ->assertJsonPath('data.name', 'Conjunto infantil bordado')
            ->assertJsonPath('data.size_range', '2 a 8')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.status_label', 'Activo')
            ->assertJsonPath('data.image_path', null);

        $model = GarmentModel::query()
            ->where('code', 'PM-23')
            ->firstOrFail();

        $this->assertDatabaseHas('garment_models', [
            'id' => $model->id,
            'code' => 'PM-23',
            'name' => 'Conjunto infantil bordado',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'garment-models',
            'action' => 'created',
            'subject_type' => GarmentModel::class,
            'subject_id' => $model->id,
        ]);
    }

    public function test_administrator_can_create_garment_model_with_image(): void
    {
        $this->authenticateAdministrator();

        $image = UploadedFile::fake()->image(
            'vestido-primavera.png',
            800,
            800
        );

        $response = $this->post(
            '/api/v1/garment-models',
            [
                'code' => 'PM-24',
                'name' => 'Vestido primavera',
                'description' => 'Vestido infantil con detalle bordado.',
                'size_range' => '4 a 10',
                'status' => 'active',
                'image' => $image,
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'PM-24')
            ->assertJsonPath('data.name', 'Vestido primavera')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.status_label', 'Activo');

        $model = GarmentModel::query()
            ->where('code', 'PM-24')
            ->firstOrFail();

        $this->assertNotNull($model->image_path);

        Storage::disk('public')->assertExists($model->image_path);
    }

    public function test_administrator_can_filter_and_search_garment_models(): void
    {
        $this->authenticateAdministrator();

        $this->createGarmentModel([
            'code' => 'PM-23',
            'name' => 'Conjunto bordado azul',
            'status' => 'active',
        ]);

        $this->createGarmentModel([
            'code' => 'PM-24',
            'name' => 'Vestido primavera',
            'status' => 'inactive',
        ]);

        $this->getJson(
            '/api/v1/garment-models?search=PM-23&status=active'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'PM-23')
            ->assertJsonPath('data.0.name', 'Conjunto bordado azul')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_administrator_can_update_garment_model_and_register_audit_log(): void
    {
        $admin = $this->authenticateAdministrator();

        $model = $this->createGarmentModel();

        $this->patchJson("/api/v1/garment-models/{$model->id}", [
            'name' => 'Conjunto infantil premium',
            'description' => 'Descripción actualizada del modelo.',
            'size_range' => '2 a 10',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Modelo de prenda actualizado correctamente.'
            )
            ->assertJsonPath('data.name', 'Conjunto infantil premium')
            ->assertJsonPath('data.size_range', '2 a 10')
            ->assertJsonPath(
                'data.description',
                'Descripción actualizada del modelo.'
            );

        $this->assertDatabaseHas('garment_models', [
            'id' => $model->id,
            'name' => 'Conjunto infantil premium',
            'size_range' => '2 a 10',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'garment-models',
            'action' => 'updated',
            'subject_type' => GarmentModel::class,
            'subject_id' => $model->id,
        ]);
    }

    public function test_administrator_can_replace_model_image_and_remove_previous_file(): void
    {
        $this->authenticateAdministrator();

        $oldImagePath = 'garment-models/old-model-image.jpg';

        Storage::disk('public')->put(
            $oldImagePath,
            'contenido de imagen anterior'
        );

        $model = $this->createGarmentModel([
            'image_path' => $oldImagePath,
        ]);

        $newImage = UploadedFile::fake()->image(
            'new-model-image.png',
            900,
            900
        );

        $response = $this->patch(
            "/api/v1/garment-models/{$model->id}",
            [
                'image' => $newImage,
            ],
            [
                'Accept' => 'application/json',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Modelo de prenda actualizado correctamente.'
            );

        $model->refresh();

        $this->assertNotSame(
            $oldImagePath,
            $model->image_path
        );

        Storage::disk('public')->assertMissing($oldImagePath);
        Storage::disk('public')->assertExists($model->image_path);
    }

    public function test_administrator_can_deactivate_and_activate_garment_model(): void
    {
        $admin = $this->authenticateAdministrator();

        $model = $this->createGarmentModel();

        $this->postJson(
            "/api/v1/garment-models/{$model->id}/deactivate"
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Modelo de prenda desactivado correctamente.'
            )
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.status_label', 'Inactivo');

        $this->assertDatabaseHas('garment_models', [
            'id' => $model->id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'garment-models',
            'action' => 'deactivated',
            'subject_type' => GarmentModel::class,
            'subject_id' => $model->id,
        ]);

        $this->postJson(
            "/api/v1/garment-models/{$model->id}/activate"
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Modelo de prenda activado correctamente.'
            )
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.status_label', 'Activo');

        $this->assertDatabaseHas('garment_models', [
            'id' => $model->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $admin->id,
            'module' => 'garment-models',
            'action' => 'activated',
            'subject_type' => GarmentModel::class,
            'subject_id' => $model->id,
        ]);
    }

    public function test_consultation_user_can_view_models_but_cannot_create_them(): void
    {
        $user = User::factory()->create([
            'username' => 'supervisor.models',
            'email' => 'supervisor.models@uyn.test',
            'status' => 'active',
        ]);

        $user->assignRole('Usuario de consulta/supervisión');

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/garment-models')
            ->assertOk();

        $this->postJson('/api/v1/garment-models', [
            'code' => 'PM-25',
            'name' => 'Modelo no autorizado',
            'size_range' => '2 a 8',
        ])->assertForbidden();
    }

    public function test_user_without_garment_model_permissions_cannot_access_catalog(): void
    {
        $user = User::factory()->create([
            'username' => 'without.models.permission',
            'email' => 'without.models.permission@uyn.test',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/garment-models')
            ->assertForbidden();

        $this->postJson('/api/v1/garment-models', [
            'code' => 'PM-25',
            'name' => 'Modelo no autorizado',
            'size_range' => '2 a 8',
        ])->assertForbidden();
    }

    public function test_garment_model_creation_requires_valid_data(): void
    {
        $this->authenticateAdministrator();

        $this->postJson('/api/v1/garment-models', [
            'code' => 'modelo inválido!',
            'name' => '',
            'size_range' => str_repeat('A', 101),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'name',
                'size_range',
            ]);

        $this->assertDatabaseCount('garment_models', 0);
    }

    public function test_garment_model_code_must_be_unique(): void
    {
        $this->authenticateAdministrator();

        $this->createGarmentModel([
            'code' => 'PM-23',
        ]);

        $this->postJson('/api/v1/garment-models', [
            'code' => 'pm-23',
            'name' => 'Modelo duplicado',
            'size_range' => '2 a 8',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('garment_models', 1);
    }
}