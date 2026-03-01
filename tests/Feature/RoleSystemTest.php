<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class RoleSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_initializes_roles_array()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'locataire',
        ]);

        $this->assertIsArray($user->roles);
        $this->assertContains('locataire', $user->roles);
        $this->assertEquals('locataire', $user->role);
    }

    public function test_user_can_fetch_roles()
    {
        $user = User::factory()->create([
            'role' => 'locataire',
            'roles' => ['locataire']
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/roles');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_role' => 'locataire',
                    'available_roles' => ['locataire']
                ]
            ]);
    }

    public function test_user_can_acquire_new_role()
    {
        $user = User::factory()->create([
            'role' => 'locataire',
            'roles' => ['locataire']
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/roles/acquire', ['role' => 'bailleur']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Félicitations ! Vous êtes maintenant aussi un bailleur'
            ]);

        $user->refresh();
        $this->assertContains('bailleur', $user->roles);
        $this->assertContains('locataire', $user->roles);
    }

    public function test_user_can_switch_active_role()
    {
        $user = User::factory()->create([
            'role' => 'locataire',
            'roles' => ['locataire', 'bailleur']
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/roles/switch', ['role' => 'bailleur']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_role' => 'bailleur'
                ]
            ]);

        $user->refresh();
        $this->assertEquals('bailleur', $user->role);
    }

    public function test_user_cannot_switch_to_unpossessed_role()
    {
        $user = User::factory()->create([
            'role' => 'locataire',
            'roles' => ['locataire']
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/roles/switch', ['role' => 'bailleur']);

        $response->assertStatus(403);
        $this->assertEquals('locataire', $user->role);
    }

    public function test_tenant_does_not_get_locataire_role_automatically_on_apply()
    {
        // Start as something else, say 'client'
        $user = User::factory()->create([
            'role' => 'client',
            'roles' => ['client']
        ]);

        $property = Property::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenant/apply', [
            'property_id' => $property->id,
            'start_date' => now()->addDays(2)->format('Y-m-d'),
            'duration_months' => 12,
            'notes' => 'Test application'
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotContains('locataire', $user->roles);
    }

    public function test_user_gets_bailleur_role_automatically_on_property_creation()
    {
        $user = User::factory()->create([
            'role' => 'locataire',
            'roles' => ['locataire']
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/properties', [
            'title' => 'New Property',
            'description' => 'Cool description',
            'price' => 150000,
            'location' => 'Yaoundé',
            'city' => 'Yaoundé',
            'type' => 'rent',
            'category' => 'Appartement'
        ]);

        $response->assertStatus(201);

        $user->refresh();
        $this->assertContains('bailleur', $user->roles);
    }
}
