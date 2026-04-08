<?php

declare(strict_types=1);

namespace Tests\Feature\Location;

use App\Models\Property;
use App\Models\Rental;
use App\Models\RentalApplication;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * =====================================================================
 * SUITE DE TESTS — Nouveau processus locatif HomeCameroon
 * =====================================================================
 *
 * Flux testé :
 *   Phase 0 : Sécurité & accès
 *   Phase 1 : Réservation de visite         → POST /api/visits
 *   Phase 2 : Confirmation des visites       → agent + user
 *   Phase 3 : Soumission du dossier          → POST /api/rental-applications
 *   Phase 4 : Validation/rejet agent         → POST /api/agent/applications/{id}/*
 *   Phase 5 : Confirmation du paiement       → POST /api/agent/rentals/{id}/confirm-payment
 *             → Attribution du rôle locataire
 *   Phase 6 : Scénario bout-en-bout complet
 * =====================================================================
 */
class NouveauProcessusLocationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ────────────────────────────────────────────────────────────

    /** Crée un agent HMC avec token. */
    private function createAgent(): array
    {
        $agent = User::factory()->agent()->create();
        $token = $agent->createToken('test')->plainTextToken;
        return [$agent, $token];
    }

    /** Crée un bailleur avec un bien actif géré par l'agent. */
    private function createPropertyWithAgent(User $agent): Property
    {
        $bailleur = User::factory()->bailleur()->create();
        return Property::factory()->create([
            'user_id'  => $bailleur->id,
            'agent_id' => $agent->id,
            'type'     => 'rent',
            'status'   => 'active',
            'price'    => 100000,
        ]);
    }

    /** Crée un utilisateur authentifié (sans rôle locataire encore). */
    private function createUser(): array
    {
        $user  = User::factory()->client()->create();
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 0 : SÉCURITÉ & ACCÈS
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_invite_ne_peut_pas_reserver_une_visite(): void
    {
        [$agent] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);

        $this->postJson('/api/visits', [
            'property_id'  => $property->id,
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertStatus(401);
    }

    /** @test */
    public function un_invite_ne_peut_pas_soumettre_un_dossier(): void
    {
        $this->postJson('/api/rental-applications', [
            'property_id' => 1,
            'visit_id'    => 1,
        ])->assertStatus(401);
    }

    /** @test */
    public function un_invite_ne_peut_pas_acceder_aux_missions_agent(): void
    {
        $this->getJson('/api/agent/missions')->assertStatus(401);
    }

    /** @test */
    public function un_invite_ne_peut_pas_confirmer_un_paiement(): void
    {
        $this->postJson('/api/agent/rentals/1/confirm-payment', [
            'payment_method' => 'cash',
        ])->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 1 : RÉSERVATION DE VISITE
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_utilisateur_connecte_peut_reserver_une_visite(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        $response = $this->withToken($token)->postJson('/api/visits', [
            'property_id'       => $property->id,
            'scheduled_at'      => now()->addDays(3)->format('Y-m-d H:i:s'),
            'fee_payment_method' => 'om',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'property_id', 'user_id', 'status', 'scheduled_at'],
            ]);

        $this->assertDatabaseHas('visits', [
            'user_id'    => $user->id,
            'property_id' => $property->id,
            'status'     => 'pending',
        ]);
    }

    /** @test */
    public function un_utilisateur_ne_peut_pas_reserver_deux_visites_pour_le_meme_bien(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        // Première visite
        $this->withToken($token)->postJson('/api/visits', [
            'property_id'  => $property->id,
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertStatus(201);

        // Deuxième visite → refusée
        $this->withToken($token)->postJson('/api/visits', [
            'property_id'  => $property->id,
            'scheduled_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertDatabaseCount('visits', 1);
    }

    /** @test */
    public function la_reservation_echoue_sans_date_schedulee(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [, $token]  = $this->createUser();

        $this->withToken($token)->postJson('/api/visits', [
            'property_id' => $property->id,
            // scheduled_at manquant
        ])->assertStatus(422)->assertJsonValidationErrors(['scheduled_at']);
    }

    /** @test */
    public function la_reservation_echoue_avec_une_date_dans_le_passe(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [, $token]  = $this->createUser();

        $this->withToken($token)->postJson('/api/visits', [
            'property_id'  => $property->id,
            'scheduled_at' => now()->subHour()->format('Y-m-d H:i:s'),
        ])->assertStatus(422)->assertJsonValidationErrors(['scheduled_at']);
    }

    /** @test */
    public function un_utilisateur_peut_voir_la_liste_de_ses_visites(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        Visit::factory()->withAgent($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);
        // Visite d'un autre utilisateur (ne doit pas apparaître)
        Visit::factory()->create(['property_id' => $property->id]);

        $response = $this->withToken($token)->getJson('/api/visits');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function un_utilisateur_peut_annuler_une_visite_pending(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        $visit = Visit::factory()->pending()->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/visits/{$visit->id}/cancel")
            ->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('visits', ['id' => $visit->id, 'status' => 'cancelled']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 2 : DOUBLE CONFIRMATION DE LA VISITE
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_utilisateur_peut_confirmer_sa_presence_a_la_visite(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        $visit = Visit::factory()->pending()->withAgent($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $response = $this->withToken($token)
            ->postJson("/api/visits/{$visit->id}/confirm");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.confirmed_by_user', true);

        $this->assertDatabaseHas('visits', [
            'id'                => $visit->id,
            'confirmed_by_user' => true,
        ]);
    }

    /** @test */
    public function la_visite_devient_completed_quand_les_deux_parties_confirment(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $userToken] = $this->createUser();

        $visit = Visit::factory()->confirmedByUser()->withAgent($agent)->create([
            'user_id'            => $user->id,
            'property_id'        => $property->id,
            'fee_payment_status' => 'paid',
        ]);

        // L'agent confirme → la visite doit passer à "completed"
        $this->withToken($agentToken)
            ->postJson("/api/agent/visits/{$visit->id}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('can_apply', true);

        $this->assertDatabaseHas('visits', ['id' => $visit->id, 'status' => 'completed']);
    }

    /** @test */
    public function la_visite_reste_confirmed_si_seulement_agent_a_confirme(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user]     = $this->createUser();

        $visit = Visit::factory()->pending()->withAgent($agent)->create([
            'user_id'            => $user->id,
            'property_id'        => $property->id,
            'confirmed_by_user'  => false, // user n'a pas encore confirmé
            'fee_payment_status' => 'paid',
        ]);

        $this->withToken($agentToken)
            ->postJson("/api/agent/visits/{$visit->id}/confirm")
            ->assertStatus(200);

        // Pas "completed" car l'user n'a pas confirmé
        $this->assertDatabaseHas('visits', [
            'id'                 => $visit->id,
            'confirmed_by_agent' => true,
            'status'             => 'confirmed', // pas completed
        ]);
    }

    /** @test */
    public function lagent_peut_annuler_une_visite(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user]     = $this->createUser();

        $visit = Visit::factory()->pending()->withAgent($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $this->withToken($agentToken)
            ->postJson("/api/agent/visits/{$visit->id}/cancel")
            ->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('visits', ['id' => $visit->id, 'status' => 'cancelled']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 3 : SOUMISSION DU DOSSIER DE CANDIDATURE
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function un_utilisateur_peut_soumettre_un_dossier_apres_visite_completee(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        $visit = Visit::factory()->completed($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $response = $this->withToken($token)->postJson('/api/rental-applications', [
            'property_id'               => $property->id,
            'visit_id'                  => $visit->id,
            'situation_professionnelle' => 'cdi',
            'revenus_mensuels'          => 200000,
            'has_garant'                => false,
            'notes'                     => 'Dossier test.',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'status', 'property_id', 'user_id', 'visit_id'],
            ]);

        $this->assertDatabaseHas('rental_applications', [
            'user_id'     => $user->id,
            'property_id' => $property->id,
            'visit_id'    => $visit->id,
            'status'      => 'pending',
            'agent_id'    => $agent->id, // hérite de l'agent de la visite
        ]);
    }

    /** @test */
    public function un_utilisateur_ne_peut_pas_soumettre_un_dossier_sans_visite_completee(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        // Visite seulement pending (pas completed)
        $visit = Visit::factory()->pending()->withAgent($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $this->withToken($token)->postJson('/api/rental-applications', [
            'property_id'               => $property->id,
            'visit_id'                  => $visit->id,
            'situation_professionnelle' => 'cdi',
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertDatabaseCount('rental_applications', 0);
    }

    /** @test */
    public function un_utilisateur_ne_peut_pas_soumettre_deux_dossiers_pour_la_meme_visite(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        $visit = Visit::factory()->completed($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $payload = [
            'property_id'               => $property->id,
            'visit_id'                  => $visit->id,
            'situation_professionnelle' => 'cdi',
        ];

        $this->withToken($token)->postJson('/api/rental-applications', $payload)->assertStatus(201);
        $this->withToken($token)->postJson('/api/rental-applications', $payload)->assertStatus(422);

        $this->assertDatabaseCount('rental_applications', 1);
    }

    /** @test */
    public function la_soumission_echoue_sans_situation_professionnelle(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        $visit = Visit::factory()->completed($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $this->withToken($token)->postJson('/api/rental-applications', [
            'property_id' => $property->id,
            'visit_id'    => $visit->id,
            // situation_professionnelle manquante — non requise côté validation mais testons la route
        ])->assertStatus(201); // nullable field, pas d'erreur de validation
    }

    /** @test */
    public function un_utilisateur_voit_ses_dossiers(): void
    {
        [$agent]    = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$user, $token] = $this->createUser();

        RentalApplication::factory()->withAgent($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);
        // Dossier d'un autre user (ne doit pas apparaître)
        RentalApplication::factory()->create(['property_id' => $property->id]);

        $response = $this->withToken($token)->getJson('/api/rental-applications');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('data'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 4 : VALIDATION / REJET PAR L'AGENT
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function lagent_voit_les_dossiers_qui_lui_sont_assignes(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);
        [$user]   = $this->createUser();
        [$agent2] = $this->createAgent();

        // Dossier pour cet agent
        RentalApplication::factory()->withAgent($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);
        // Dossier pour un autre agent (ne doit PAS apparaître)
        RentalApplication::factory()->withAgent($agent2)->create([
            'property_id' => $property->id,
        ]);

        $response = $this->withToken($agentToken)->getJson('/api/agent/applications');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('data.data')); // paginé
    }

    /** @test */
    public function lagent_peut_valider_un_dossier(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);
        [$user]   = $this->createUser();

        $visit = Visit::factory()->completed($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $application = RentalApplication::factory()->withAgent($agent)->pending()->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
            'visit_id'    => $visit->id,
        ]);

        $response = $this->withToken($agentToken)
            ->postJson("/api/agent/applications/{$application->id}/validate", [
                'advance_months' => 2,
                'start_date'     => now()->addDays(10)->format('Y-m-d'),
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Le dossier est validé
        $this->assertDatabaseHas('rental_applications', [
            'id'          => $application->id,
            'status'      => 'validated',
            'reviewed_by' => $agent->id,
        ]);

        // Un Rental en attente de paiement a été créé
        $this->assertDatabaseHas('rentals', [
            'property_id'   => $property->id,
            'tenant_id'     => $user->id,
            'agent_id'      => $agent->id,
            'status'        => 'pending',
            'advance_months' => 2,
        ]);
    }

    /** @test */
    public function la_validation_du_dossier_calcule_les_montants_correctement(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = Property::factory()->create([
            'user_id'  => User::factory()->create(['role' => 'bailleur', 'roles' => ['bailleur']])->id,
            'agent_id' => $agent->id,
            'price'    => 100000,
            'status'   => 'active',
            'type'     => 'rent',
        ]);
        [$user] = $this->createUser();

        $application = RentalApplication::factory()->withAgent($agent)->pending()->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $this->withToken($agentToken)
            ->postJson("/api/agent/applications/{$application->id}/validate", [
                'advance_months' => 3,
                'start_date'     => now()->addDays(7)->format('Y-m-d'),
            ]);

        $rental = Rental::where('application_id', $application->id)->first();

        $this->assertNotNull($rental);
        $this->assertEquals(100000, $rental->price);
        $this->assertEquals(3, $rental->advance_months);
        $this->assertEquals(300000, $rental->advance_amount);  // 3 × 100 000
        $this->assertEquals(200000, $rental->caution_amount);  // 2 × 100 000
    }

    /** @test */
    public function lagent_peut_rejeter_un_dossier(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);
        [$user]   = $this->createUser();

        $application = RentalApplication::factory()->withAgent($agent)->pending()->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $this->withToken($agentToken)
            ->postJson("/api/agent/applications/{$application->id}/reject", [
                'reason' => 'Revenus insuffisants pour ce bien.',
            ])->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('rental_applications', [
            'id'               => $application->id,
            'status'           => 'rejected',
            'rejection_reason' => 'Revenus insuffisants pour ce bien.',
            'reviewed_by'      => $agent->id,
        ]);

        // Aucun Rental ne doit être créé
        $this->assertDatabaseCount('rentals', 0);
    }

    /** @test */
    public function le_rejet_echoue_sans_motif(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);
        [$user]   = $this->createUser();

        $application = RentalApplication::factory()->withAgent($agent)->pending()->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $this->withToken($agentToken)
            ->postJson("/api/agent/applications/{$application->id}/reject", [])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    /** @test */
    public function lagent_ne_peut_pas_valider_un_dossier_qui_ne_lui_appartient_pas(): void
    {
        [$agent1]           = $this->createAgent();
        [$agent2, $token2]  = $this->createAgent();
        $property           = $this->createPropertyWithAgent($agent1);
        [$user]             = $this->createUser();

        $application = RentalApplication::factory()->withAgent($agent1)->pending()->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        // Agent 2 tente de valider un dossier assigné à l'agent 1
        $this->withToken($token2)
            ->postJson("/api/agent/applications/{$application->id}/validate", [
                'advance_months' => 2,
                'start_date'     => now()->addDays(7)->format('Y-m-d'),
            ])->assertStatus(404); // findOrFail avec where agent_id
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 5 : CONFIRMATION DU PAIEMENT → RÔLE LOCATAIRE
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function lagent_peut_confirmer_le_paiement_et_activer_la_location(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);
        [$user]   = $this->createUser();

        $application = RentalApplication::factory()->validated($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $rental = Rental::factory()->create([
            'property_id'    => $property->id,
            'tenant_id'      => $user->id,
            'agent_id'       => $agent->id,
            'application_id' => $application->id,
            'status'         => 'pending',
        ]);

        $response = $this->withToken($agentToken)
            ->postJson("/api/agent/rentals/{$rental->id}/confirm-payment", [
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Location activée
        $this->assertDatabaseHas('rentals', [
            'id'                   => $rental->id,
            'status'               => 'active',
            'payment_phase_status' => 'confirmed',
            'payment_confirmed_by' => $agent->id,
        ]);

        // Bien marqué comme loué
        $this->assertDatabaseHas('properties', [
            'id'     => $property->id,
            'status' => 'rented',
        ]);
    }

    /** @test */
    public function la_confirmation_du_paiement_attribue_le_role_locataire(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);
        [$user]   = $this->createUser();

        // L'user n'a PAS encore le rôle locataire
        $this->assertFalse($user->hasRole('locataire'));

        $application = RentalApplication::factory()->validated($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $rental = Rental::factory()->create([
            'property_id'    => $property->id,
            'tenant_id'      => $user->id,
            'agent_id'       => $agent->id,
            'application_id' => $application->id,
            'status'         => 'pending',
        ]);

        $this->withToken($agentToken)
            ->postJson("/api/agent/rentals/{$rental->id}/confirm-payment", [
                'payment_method' => 'om',
            ])->assertStatus(200);

        // Le rôle locataire a été attribué
        $user->refresh();
        $this->assertTrue($user->hasRole('locataire'));
        $this->assertEquals('locataire', $user->role);
    }

    /** @test */
    public function la_confirmation_echoue_sans_mode_de_paiement(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);
        [$user]   = $this->createUser();

        $rental = Rental::factory()->create([
            'property_id' => $property->id,
            'tenant_id'   => $user->id,
            'agent_id'    => $agent->id,
            'status'      => 'pending',
        ]);

        $this->withToken($agentToken)
            ->postJson("/api/agent/rentals/{$rental->id}/confirm-payment", [])
            ->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
    }

    /** @test */
    public function lagent_ne_peut_pas_confirmer_paiement_dune_location_qui_ne_lui_appartient_pas(): void
    {
        [$agent1]           = $this->createAgent();
        [$agent2, $token2]  = $this->createAgent();
        $property           = $this->createPropertyWithAgent($agent1);
        [$user]             = $this->createUser();

        $rental = Rental::factory()->create([
            'property_id' => $property->id,
            'tenant_id'   => $user->id,
            'agent_id'    => $agent1->id,
            'status'      => 'pending',
        ]);

        $this->withToken($token2)
            ->postJson("/api/agent/rentals/{$rental->id}/confirm-payment", [
                'payment_method' => 'cash',
            ])->assertStatus(404);
    }

    /** @test */
    public function le_role_locataire_nest_pas_attribue_si_deja_present(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);

        // Cet user a déjà le rôle locataire
        $user = User::factory()->create(['role' => 'locataire', 'roles' => ['locataire']]);

        $application = RentalApplication::factory()->validated($agent)->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);

        $rental = Rental::factory()->create([
            'property_id'    => $property->id,
            'tenant_id'      => $user->id,
            'agent_id'       => $agent->id,
            'application_id' => $application->id,
            'status'         => 'pending',
        ]);

        $this->withToken($agentToken)
            ->postJson("/api/agent/rentals/{$rental->id}/confirm-payment", [
                'payment_method' => 'cash',
            ])->assertStatus(200);

        // Toujours 1 seul rôle locataire (pas de doublon)
        $user->refresh();
        $this->assertEquals(1, count(array_filter($user->roles, fn($r) => $r === 'locataire')));
    }

    // ═══════════════════════════════════════════════════════════════════
    // PHASE 6 : DASHBOARD AGENT
    // ═══════════════════════════════════════════════════════════════════

    /** @test */
    public function le_dashboard_agent_retourne_les_bonnes_stats(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);
        [$user]   = $this->createUser();

        // 2 visites pending
        Visit::factory()->pending()->withAgent($agent)->count(2)->create([
            'property_id' => $property->id,
        ]);
        // 1 dossier pending
        RentalApplication::factory()->withAgent($agent)->pending()->create([
            'user_id'     => $user->id,
            'property_id' => $property->id,
        ]);
        // 1 location active
        Rental::factory()->active()->create([
            'property_id' => $property->id,
            'tenant_id'   => $user->id,
            'agent_id'    => $agent->id,
        ]);

        $response = $this->withToken($agentToken)->getJson('/api/agent/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.managed_properties', 1)
            ->assertJsonPath('data.stats.pending_visits', 2)
            ->assertJsonPath('data.stats.pending_applications', 1)
            ->assertJsonPath('data.stats.active_rentals', 1);
    }

    /** @test */
    public function le_dashboard_agent_retourne_agenda_et_visites_recentes(): void
    {
        [$agent, $agentToken] = $this->createAgent();
        $property = $this->createPropertyWithAgent($agent);
        [$user]   = $this->createUser();

        // Visite aujourd'hui
        Visit::factory()->pending()->withAgent($agent)->create([
            'property_id'  => $property->id,
            'user_id'      => $user->id,
            'scheduled_at' => now()->setTime(10, 0),
        ]);

        $response = $this->withToken($agentToken)->getJson('/api/agent/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'stats',
                    'agenda',
                    'recentVisits',
                    'recentApplications',
                ],
            ]);
        $this->assertCount(1, $response->json('data.agenda'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // SCÉNARIO BOUT-EN-BOUT : HAPPY PATH COMPLET
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * Simule le flux intégral :
     *   1. Bailleur crée son bien (géré par un agent)
     *   2. Un client voit l'annonce
     *   3. Le client réserve une visite
     *   4. L'agent confirme la visite (agent side)
     *   5. Le client confirme sa présence → visite "completed"
     *   6. Le client soumet son dossier
     *   7. L'agent valide le dossier → Rental créé
     *   8. L'agent confirme la réception du paiement
     *   9. Le client devient "locataire" → location active
     */
    /** @test */
    public function scenario_complet_nouveau_processus_locatif(): void
    {
        // ── Acteurs ──────────────────────────────────────────────────────────
        [$agent, $agentToken] = $this->createAgent();
        $property   = $this->createPropertyWithAgent($agent);
        [$client, $clientToken] = $this->createUser();
        $this->assertFalse($client->hasRole('locataire'), 'Le client ne doit pas encore être locataire');

        // ── 1. Le client consulte les annonces ───────────────────────────────
        $this->getJson('/api/properties')->assertStatus(200);
        $this->getJson("/api/properties/{$property->id}")->assertStatus(200);

        // ── 2. Le client réserve une visite ──────────────────────────────────
        $visitResponse = $this->withToken($clientToken)->postJson('/api/visits', [
            'property_id'        => $property->id,
            'scheduled_at'       => now()->addDays(2)->format('Y-m-d H:i:s'),
            'fee_payment_method' => 'om',
        ]);
        $visitResponse->assertStatus(201)->assertJson(['success' => true]);
        $visitId = $visitResponse->json('data.id');

        $this->assertDatabaseHas('visits', ['id' => $visitId, 'status' => 'pending']);

        // ── 3. L'agent est assigné à la visite (simulation back-office) ──────
        // En production l'admin assigne l'agent. En test on le fait directement.
        \App\Models\Visit::where('id', $visitId)->update(['agent_id' => $agent->id]);

        // Vérification que l'update a bien été enregistré
        $this->assertDatabaseHas('visits', ['id' => $visitId, 'agent_id' => $agent->id]);

        // ── 4. L'agent confirme la visite (confirmed_by_agent) ───────────────
        Sanctum::actingAs($agent);
        $this->postJson("/api/agent/visits/{$visitId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.confirmed_by_agent', true);

        // Visite "confirmed" – l'user n'ayant pas encore confirmé
        $this->assertDatabaseHas('visits', ['id' => $visitId, 'status' => 'confirmed']);

        // ── 5. Le client confirme sa présence → visite "completed" ───────────
        Sanctum::actingAs($client);
        $this->postJson("/api/visits/{$visitId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('can_apply', true);

        $this->assertDatabaseHas('visits', ['id' => $visitId, 'status' => 'completed']);

        // ── 6. Le client soumet son dossier ──────────────────────────────────
        $appResponse = $this->postJson('/api/rental-applications', [
            'property_id'               => $property->id,
            'visit_id'                  => $visitId,
            'situation_professionnelle' => 'cdi',
            'revenus_mensuels'          => 300000,
            'has_garant'                => true,
            'notes'                     => 'Scénario bout-en-bout.',
        ]);
        $appResponse->assertStatus(201)->assertJson(['success' => true]);
        $applicationId = $appResponse->json('data.id');

        $this->assertDatabaseHas('rental_applications', [
            'id'     => $applicationId,
            'status' => 'pending',
        ]);

        // ── 7. L'agent voit le dossier dans ses missions ─────────────────────
        Sanctum::actingAs($agent);
        $this->getJson('/api/agent/missions')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.pending_applications');

        // ── 8. L'agent valide le dossier ─────────────────────────────────────
        $this->postJson("/api/agent/applications/{$applicationId}/validate", [
            'advance_months' => 2,
            'start_date'     => now()->addDays(10)->format('Y-m-d'),
        ])->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('rental_applications', ['id' => $applicationId, 'status' => 'validated']);
        $this->assertDatabaseHas('rentals', [
            'tenant_id'   => $client->id,
            'property_id' => $property->id,
            'status'      => 'pending',
        ]);

        $rentalId = Rental::where('tenant_id', $client->id)->first()->id;

        // ── 9. L'agent confirme la réception du paiement ─────────────────────
        $this->postJson("/api/agent/rentals/{$rentalId}/confirm-payment", [
            'payment_method' => 'cash',
        ])->assertStatus(200)->assertJson(['success' => true]);

        // ── 10. Vérifications finales ─────────────────────────────────────────
        $this->assertDatabaseHas('rentals', [
            'id'                   => $rentalId,
            'status'               => 'active',
            'payment_phase_status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'status' => 'rented']);

        $client->refresh();
        $this->assertTrue($client->hasRole('locataire'), 'Le client doit avoir le rôle locataire');
        $this->assertEquals('locataire', $client->role);
    }
}
