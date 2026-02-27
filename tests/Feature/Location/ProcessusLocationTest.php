<?php

declare(strict_types=1);

namespace Tests\Feature\Location;

use App\Models\Property;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * =====================================================================
 * SUITE DE TESTS — Processus complet de location
 * =====================================================================
 * Couvre :
 *  1. Soumission d'une demande (apply)           → POST /api/tenant/apply
 *  2. Doublons de demande                        → 422 si déjà en cours
 *  3. Accès non authentifié                      → 401
 *  4. Validation des champs requis               → 422
 *  5. Liste des locations locataire              → GET /api/tenant/rentals
 *  6. Dashboard locataire                        → GET /api/tenant/dashboard
 *  7. Dashboard bailleur (candidatures reçues)   → GET /api/bailleur/applications
 *  8. Mise à jour du statut par le bailleur      → POST /api/bailleur/applications/{id}/status
 *  9. Accès aux candidatures par un non-bailleur → 403/401
 * 10. Scénario bout-en-bout (happy path)         → End-to-end
 * =====================================================================
 */
class ProcessusLocationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ────────────────────────────────────────────────────────────

    /** Crée un bailleur avec un bien actif. */
    private function createBailleurWithProperty(): array
    {
        $bailleur = User::factory()->create(['role' => 'bailleur']);
        $property = Property::factory()->create([
            'user_id' => $bailleur->id,
            'type' => 'rent',
            'status' => 'active',
            'price' => 100000,
        ]);

        return [$bailleur, $property];
    }

    /** Crée un locataire authentifié et retourne le token. */
    private function createTenant(): array
    {
        $tenant = User::factory()->create(['role' => 'locataire']);
        $token = $tenant->createToken('test')->plainTextToken;

        return [$tenant, $token];
    }

    /** Payload valide pour une demande de location. */
    private function validPayload(int $propertyId): array
    {
        return [
            'property_id' => $propertyId,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'duration_months' => 12,
            'notes' => 'Test de demande de location.',
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1. SOUMISSION D'UNE DEMANDE DE LOCATION
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_locataire_peut_soumettre_une_demande_de_location(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $token] = $this->createTenant();

        $response = $this->withToken($token)
            ->postJson('/api/tenant/apply', $this->validPayload($property->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Votre demande de location a été envoyée avec succès.',
            ]);

        $this->assertDatabaseHas('rentals', [
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'status' => 'pending',
            'price' => 100000,
        ]);
    }

    /** @test */
    public function la_demande_cree_la_bonne_date_de_fin(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $token] = $this->createTenant();

        $startDate = now()->addDays(10)->format('Y-m-d');

        $this->withToken($token)->postJson('/api/tenant/apply', [
            'property_id' => $property->id,
            'start_date' => $startDate,
            'duration_months' => 6,
        ]);

        $rental = Rental::where('tenant_id', $tenant->id)->first();
        $expectedEnd = date('Y-m-d', strtotime($startDate . ' + 6 months'));

        $this->assertEquals($expectedEnd, $rental->end_date->format('Y-m-d'));
    }

    /** @test */
    public function le_prix_de_la_location_correspond_au_loyer_du_bien(): void
    {
        [, $property] = $this->createBailleurWithProperty(); // price = 100000
        [, $token] = $this->createTenant();

        $this->withToken($token)->postJson('/api/tenant/apply', $this->validPayload($property->id));

        $rental = Rental::latest()->first();
        $this->assertEquals(100000, $rental->price);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. PRÉVENTION DES DOUBLONS
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_locataire_ne_peut_pas_faire_deux_demandes_pour_le_meme_bien(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $token] = $this->createTenant();

        // Première demande → succès
        $this->withToken($token)->postJson('/api/tenant/apply', $this->validPayload($property->id))
            ->assertStatus(200);

        // Deuxième demande → rejetée
        $response = $this->withToken($token)
            ->postJson('/api/tenant/apply', $this->validPayload($property->id));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Vous avez déjà une demande en cours pour ce bien.',
            ]);

        $this->assertDatabaseCount('rentals', 1);
    }

    /** @test */
    public function un_locataire_peut_re_postuler_apres_annulation(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $token] = $this->createTenant();

        // Ancienne demande annulée
        Rental::factory()->cancelled()->create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        // Nouvelle demande → doit être acceptée
        $response = $this->withToken($token)
            ->postJson('/api/tenant/apply', $this->validPayload($property->id));

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseCount('rentals', 2);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. SÉCURITÉ — ACCÈS NON AUTHENTIFIÉ
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_utilisateur_non_connecte_ne_peut_pas_postuler(): void
    {
        [, $property] = $this->createBailleurWithProperty();

        $this->postJson('/api/tenant/apply', $this->validPayload($property->id))
            ->assertStatus(401);
    }

    /** @test */
    public function un_utilisateur_non_connecte_ne_peut_pas_voir_ses_locations(): void
    {
        $this->getJson('/api/tenant/rentals')->assertStatus(401);
    }

    /** @test */
    public function un_utilisateur_non_connecte_ne_peut_pas_voir_le_dashboard(): void
    {
        $this->getJson('/api/tenant/dashboard')->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. VALIDATION DES CHAMPS
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function la_demande_echoue_sans_property_id(): void
    {
        [, $token] = $this->createTenant();

        $this->withToken($token)->postJson('/api/tenant/apply', [
            'start_date' => now()->addDays(2)->format('Y-m-d'),
            'duration_months' => 12,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['property_id']);
    }

    /** @test */
    public function la_demande_echoue_avec_un_bien_inexistant(): void
    {
        [, $token] = $this->createTenant();

        $this->withToken($token)->postJson('/api/tenant/apply', [
            'property_id' => 99999,
            'start_date' => now()->addDays(2)->format('Y-m-d'),
            'duration_months' => 12,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['property_id']);
    }

    /** @test */
    public function la_demande_echoue_sans_start_date(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [, $token] = $this->createTenant();

        $this->withToken($token)->postJson('/api/tenant/apply', [
            'property_id' => $property->id,
            'duration_months' => 12,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    /** @test */
    public function la_demande_echoue_avec_une_date_dans_le_passe(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [, $token] = $this->createTenant();

        $this->withToken($token)->postJson('/api/tenant/apply', [
            'property_id' => $property->id,
            'start_date' => now()->subDays(1)->format('Y-m-d'),
            'duration_months' => 12,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    /** @test */
    public function la_demande_echoue_sans_duration_months(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [, $token] = $this->createTenant();

        $this->withToken($token)->postJson('/api/tenant/apply', [
            'property_id' => $property->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['duration_months']);
    }

    /** @test */
    public function la_demande_echoue_avec_duration_months_negatif(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [, $token] = $this->createTenant();

        $this->withToken($token)->postJson('/api/tenant/apply', [
            'property_id' => $property->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'duration_months' => -1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['duration_months']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 5. LISTE DES LOCATIONS (LOCATAIRE)
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_locataire_voit_uniquement_ses_locations(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $token] = $this->createTenant();
        [$otherTenant] = $this->createTenant();

        Rental::factory()->active()->create(['tenant_id' => $tenant->id,      'property_id' => $property->id]);
        Rental::factory()->pending()->create(['tenant_id' => $tenant->id,     'property_id' => $property->id]);
        Rental::factory()->active()->create(['tenant_id' => $otherTenant->id, 'property_id' => $property->id]);

        $response = $this->withToken($token)->getJson('/api/tenant/rentals');

        $response->assertStatus(200)->assertJson(['success' => true]);
        // Seulement les 2 locations du locataire
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function la_liste_des_locations_est_vide_pour_un_nouveau_locataire(): void
    {
        [, $token] = $this->createTenant();

        $response = $this->withToken($token)->getJson('/api/tenant/rentals');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => []]);
    }

    /** @test */
    public function les_locations_incluent_les_informations_du_bien(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $token] = $this->createTenant();

        Rental::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        $response = $this->withToken($token)->getJson('/api/tenant/rentals');

        $response->assertStatus(200);
        $rental = $response->json('data.0');
        $this->assertArrayHasKey('property', $rental);
        $this->assertEquals($property->id, $rental['property']['id']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. DASHBOARD LOCATAIRE
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function le_dashboard_locataire_retourne_les_stats_correctes(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $token] = $this->createTenant();

        Rental::factory()->active()->create(['tenant_id' => $tenant->id, 'property_id' => $property->id]);

        $response = $this->withToken($token)->getJson('/api/tenant/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'stats' => ['active_rentals_count' => 1],
                ],
            ]);
    }

    /** @test */
    public function le_dashboard_locataire_retourne_la_location_active(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $token] = $this->createTenant();

        $rental = Rental::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        $response = $this->withToken($token)->getJson('/api/tenant/dashboard');

        $response->assertStatus(200);
        $this->assertEquals($rental->id, $response->json('data.active_rental.id'));
    }

    /** @test */
    public function le_dashboard_locataire_sans_location_active_retourne_null(): void
    {
        [, $token] = $this->createTenant();

        $response = $this->withToken($token)->getJson('/api/tenant/dashboard');

        $response->assertStatus(200);
        $this->assertNull($response->json('data.active_rental'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // 7. CANDIDATURES CÔTÉ BAILLEUR
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_bailleur_voit_les_candidatures_pour_ses_biens(): void
    {
        [$bailleur, $property] = $this->createBailleurWithProperty();
        $bailleurToken = $bailleur->createToken('test')->plainTextToken;

        [$tenant] = $this->createTenant();
        Rental::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        $response = $this->withToken($bailleurToken)->getJson('/api/bailleur/applications');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    /** @test */
    public function un_bailleur_ne_voit_pas_les_candidatures_des_autres_bailleurs(): void
    {
        [$bailleur1, $property1] = $this->createBailleurWithProperty();
        [$bailleur2, $property2] = $this->createBailleurWithProperty();
        $token2 = $bailleur2->createToken('test')->plainTextToken;

        [$tenant] = $this->createTenant();

        // Candidature uniquement pour le bien du bailleur 1
        Rental::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'property_id' => $property1->id,
        ]);

        $response = $this->withToken($token2)->getJson('/api/bailleur/applications');

        $response->assertStatus(200);
        // Bailleur 2 ne doit pas voir les candidatures du bailleur 1
        $applicationPropertyIds = collect($response->json('data'))->pluck('property_id')->toArray();
        $this->assertNotContains($property1->id, $applicationPropertyIds);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 8. MISE À JOUR DU STATUT PAR LE BAILLEUR
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_bailleur_peut_accepter_une_candidature(): void
    {
        [$bailleur, $property] = $this->createBailleurWithProperty();
        $bailleurToken = $bailleur->createToken('test')->plainTextToken;
        [$tenant] = $this->createTenant();

        $rental = Rental::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        $response = $this->withToken($bailleurToken)
            ->postJson("/api/bailleur/applications/{$rental->id}/status", [
                'status' => 'active',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('rentals', ['id' => $rental->id, 'status' => 'active']);
    }

    /** @test */
    public function un_bailleur_peut_rejeter_une_candidature(): void
    {
        [$bailleur, $property] = $this->createBailleurWithProperty();
        $bailleurToken = $bailleur->createToken('test')->plainTextToken;
        [$tenant] = $this->createTenant();

        $rental = Rental::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        $response = $this->withToken($bailleurToken)
            ->postJson("/api/bailleur/applications/{$rental->id}/status", [
                'status' => 'cancelled',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('rentals', ['id' => $rental->id, 'status' => 'cancelled']);
    }

    /** @test */
    public function un_locataire_ne_peut_pas_modifier_le_statut_dune_candidature(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $tenantToken] = $this->createTenant();

        $rental = Rental::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        // Un locataire tente d'accéder à l'API bailleur.
        // Comme il est authentifié mais n'est pas le bailleur du bien,
        // le contrôleur retourne 403 (vérification explicite de l'ID du propriétaire).
        $this->withToken($tenantToken)
            ->postJson("/api/bailleur/applications/{$rental->id}/status", ['status' => 'active'])
            ->assertStatus(403);
    }

    /** @test */
    public function un_invité_ne_peut_pas_accéder_aux_applications_bailleur(): void
    {
        $this->getJson('/api/bailleur/applications')->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 9. LISTE DES LOCATIONS (STRUCTURE DE RÉPONSE)
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function la_reponse_api_rentals_a_la_bonne_structure(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [$tenant, $token] = $this->createTenant();

        Rental::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        $response = $this->withToken($token)->getJson('/api/tenant/rentals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'tenant_id',
                        'property_id',
                        'price',
                        'status',
                        'start_date',
                        'end_date',
                    ],
                ],
            ]);
    }

    /** @test */
    public function la_reponse_api_apply_retourne_les_donnees_de_la_location(): void
    {
        [, $property] = $this->createBailleurWithProperty();
        [, $token] = $this->createTenant();

        $response = $this->withToken($token)->postJson('/api/tenant/apply', $this->validPayload($property->id));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'status', 'price', 'start_date', 'end_date'],
            ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 10. SCÉNARIO BOUT-EN-BOUT (HAPPY PATH)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @test
     * Simule le parcours complet :
     *   Locataire voit le bien → soumet un dossier → bailleur l'accepte
     *   → la location apparaît active dans le dashboard locataire.
     */
    public function scenario_complet_de_location(): void
    {
        // 1. Créer un bailleur avec un bien
        [$bailleur, $property] = $this->createBailleurWithProperty();
        $bailleurToken = $bailleur->createToken('test')->plainTextToken;

        // 2. Créer un locataire
        [$tenant, $tenantToken] = $this->createTenant();

        // 3. Le locataire consulte les annonces
        $this->getJson('/api/properties')->assertStatus(200);
        $this->getJson("/api/properties/{$property->id}")->assertStatus(200);

        // 4. Le locataire soumet sa demande
        $applyResponse = $this->withToken($tenantToken)
            ->postJson('/api/tenant/apply', [
                'property_id' => $property->id,
                'start_date' => now()->addDays(10)->format('Y-m-d'),
                'duration_months' => 12,
                'notes' => 'Scénario bout-en-bout.',
            ]);
        $applyResponse->assertStatus(200)->assertJson(['success' => true]);
        $rentalId = $applyResponse->json('data.id');

        // 5. Le dashboard locataire montre 0 location active (encore pending)
        $this->withToken($tenantToken)->getJson('/api/tenant/dashboard')
            ->assertJsonPath('data.stats.active_rentals_count', 0);

        // 6. Le bailleur voit la candidature
        $appsResponse = $this->withToken($bailleurToken)->getJson('/api/bailleur/applications');
        $appsResponse->assertStatus(200);

        if (count($appsResponse->json('data')) === 0) {
            dump('WARNING: Bailleur sees 0 applications!');
            dump('Bailleur ID: ' . $bailleur->id);
            dump('Property Owner ID: ' . $property->user_id);
            dump('Applications in DB: ', Rental::all()->toArray());
            dump('Full Response: ', $appsResponse->json());
        }

        $appsResponse->assertJsonCount(1, 'data');

        // 7. Le bailleur accepte la candidature
        $response = $this->withToken($bailleurToken)
            ->postJson("/api/bailleur/applications/{$rentalId}/status", ['status' => 'active']);

        if ($response->status() !== 200) {
            dump('Bailleur ID: ' . $bailleur->id);
            dump('Rental ID: ' . $rentalId);
            dump('Rental from DB: ', Rental::find($rentalId)?->toArray());
            dump('Property from DB: ', Property::find($property->id)?->toArray());
            dump('Response body: ', $response->json());
        }

        $response->assertStatus(200)->assertJson(['success' => true]);

        // 8. La BDD reflète l'acceptation
        $this->assertDatabaseHas('rentals', ['id' => $rentalId, 'status' => 'active']);

        // 9. Le dashboard locataire montre maintenant 1 location active
        $this->withToken($tenantToken)->getJson('/api/tenant/dashboard')
            ->assertJsonPath('data.stats.active_rentals_count', 1);

        // 10. La liste des locations du locataire contient 1 entrée
        $this->withToken($tenantToken)->getJson('/api/tenant/rentals')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
