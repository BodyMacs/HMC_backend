<?php

declare(strict_types=1);

namespace Tests\Feature\Location;

use App\Models\Property;
use App\Models\Rental;
use App\Models\RentalApplication;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * =====================================================================
 * SUITE DE TESTS — Nouveau Processus Locatif + Rôles Corrigés
 * =====================================================================
 *
 * Couvre spécifiquement les corrections apportées :
 *   A. ProspectController — routes /api/prospect/*
 *   B. BailleurController — lecture seule (plus d'actions)
 *   C. Sécurité — le bailleur ne peut plus modifier le processus
 * =====================================================================
 */
class ProcessusRolesTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function makeAgent(): array
    {
        $agent = User::factory()->create([
            'role'  => 'agent',
            'roles' => ['agent'],
        ]);
        return [$agent, $agent->createToken('test')->plainTextToken];
    }

    private function makeBailleur(): array
    {
        $bailleur = User::factory()->create([
            'role'  => 'bailleur',
            'roles' => ['bailleur'],
        ]);
        return [$bailleur, $bailleur->createToken('test')->plainTextToken];
    }

    private function makeClient(): array
    {
        $client = User::factory()->create([
            'role'  => 'client',
            'roles' => ['client'],
        ]);
        return [$client, $client->createToken('test')->plainTextToken];
    }

    private function makeProperty(User $bailleur, User $agent): Property
    {
        return Property::factory()->create([
            'user_id'  => $bailleur->id,
            'agent_id' => $agent->id,
            'status'   => 'active',
            'type'     => 'rent',
            'price'    => 80000,
        ]);
    }

    private function makeCompletedVisit(Property $property, User $prospect, User $agent): Visit
    {
        return Visit::factory()->create([
            'property_id'        => $property->id,
            'user_id'            => $prospect->id,
            'agent_id'           => $agent->id,
            'status'             => 'completed',
            'confirmed_by_user'  => true,
            'confirmed_by_agent' => true,
            'fee_payment_status' => 'paid',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // A. ProspectController — Phase 1 : VISITES
    // ═══════════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prospect_peut_reserver_une_visite_via_prospect_route(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client, $token] = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);

        $response = $this->withToken($token)->postJson('/api/prospect/visits', [
            'property_id'  => $property->id,
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('visits', [
            'property_id' => $property->id,
            'user_id'     => $client->id,
            'agent_id'    => $agent->id, // hérite de l'agent du bien
            'status'      => 'pending',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prospect_peut_voir_ses_visites(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client, $token] = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);

        Visit::factory()->create([
            'property_id' => $property->id,
            'user_id'     => $client->id,
            'agent_id'    => $agent->id,
            'status'      => 'pending',
        ]);

        $response = $this->withToken($token)->getJson('/api/prospect/visits');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('data'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_suspect_ne_peut_pas_reserver_pour_un_bien_inactif(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [, $token]       = $this->makeClient();

        $property = Property::factory()->create([
            'user_id'  => $bailleur->id,
            'agent_id' => $agent->id,
            'status'   => 'rented', // bien déjà loué
            'type'     => 'rent',
        ]);

        $this->withToken($token)->postJson('/api/prospect/visits', [
            'property_id'  => $property->id,
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertStatus(404); // bien introuvable car status != active
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prospect_peut_confirmer_sa_visite(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client, $token] = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);

        $visit = Visit::factory()->create([
            'property_id'        => $property->id,
            'user_id'            => $client->id,
            'agent_id'           => $agent->id,
            'status'             => 'confirmed',
            'confirmed_by_user'  => false,
            'confirmed_by_agent' => false,
        ]);

        $response = $this->withToken($token)->postJson("/api/prospect/visits/{$visit->id}/confirm");

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('visits', [
            'id'                => $visit->id,
            'confirmed_by_user' => true,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function quand_les_deux_confirment_la_visite_est_completed(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client, $token] = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);

        // Agent a déjà confirmé
        $visit = Visit::factory()->create([
            'property_id'        => $property->id,
            'user_id'            => $client->id,
            'agent_id'           => $agent->id,
            'status'             => 'confirmed',
            'confirmed_by_user'  => false,
            'confirmed_by_agent' => true,
        ]);

        $response = $this->withToken($token)->postJson("/api/prospect/visits/{$visit->id}/confirm");

        $response->assertStatus(200)
            ->assertJson(['both_confirmed' => true, 'can_apply' => true]);

        $this->assertDatabaseHas('visits', [
            'id'     => $visit->id,
            'status' => 'completed',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prospect_ne_peut_pas_confirmer_la_visite_de_quelquun_dautre(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client]        = $this->makeClient();
        [, $otherToken]  = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);

        $visit = Visit::factory()->create([
            'property_id' => $property->id,
            'user_id'     => $client->id,
            'agent_id'    => $agent->id,
            'status'      => 'confirmed',
        ]);

        $this->withToken($otherToken)
            ->postJson("/api/prospect/visits/{$visit->id}/confirm")
            ->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════
    // A. ProspectController — Phase 2 : DOSSIERS
    // ═══════════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prospect_peut_creer_un_dossier_apres_visite_completee(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client, $token] = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);
        $visit           = $this->makeCompletedVisit($property, $client, $agent);

        $response = $this->withToken($token)->postJson('/api/prospect/applications', [
            'visit_id'                  => $visit->id,
            'situation_professionnelle' => 'cdi',
            'revenus_mensuels'          => 300000,
            'has_garant'                => false,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('rental_applications', [
            'property_id' => $property->id,
            'user_id'     => $client->id,
            'visit_id'    => $visit->id,
            'agent_id'    => $agent->id,
            'status'      => 'pending',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prospect_ne_peut_pas_creer_un_dossier_sans_visite_completed(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client, $token] = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);

        $visit = Visit::factory()->create([
            'property_id'        => $property->id,
            'user_id'            => $client->id,
            'agent_id'           => $agent->id,
            'status'             => 'confirmed', // pas completed !
            'confirmed_by_user'  => true,
            'confirmed_by_agent' => false,
        ]);

        $this->withToken($token)->postJson('/api/prospect/applications', [
            'visit_id' => $visit->id,
        ])->assertStatus(404); // visite non trouvée avec status=completed
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prospect_peut_mettre_a_jour_son_dossier_et_le_passer_en_under_review(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client, $token] = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);
        $visit           = $this->makeCompletedVisit($property, $client, $agent);

        $application = RentalApplication::factory()->create([
            'property_id' => $property->id,
            'user_id'     => $client->id,
            'visit_id'    => $visit->id,
            'agent_id'    => $agent->id,
            'status'      => 'pending',
        ]);

        $response = $this->withToken($token)->putJson("/api/prospect/applications/{$application->id}", [
            'signed_by_applicant' => true,
            'documents'           => [
                ['type' => 'cin_recto', 'path' => 'docs/cin_recto.jpg'],
                ['type' => 'fiche_salaire', 'path' => 'docs/salaire.pdf'],
            ],
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Statut doit passer à under_review car documents + signature
        $application->refresh();
        $this->assertEquals('under_review', $application->status);
        $this->assertTrue($application->signed_by_applicant);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prospect_peut_voir_ses_dossiers(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client, $token] = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);

        RentalApplication::factory()->create([
            'user_id'     => $client->id,
            'property_id' => $property->id,
            'agent_id'    => $agent->id,
        ]);
        // Dossier d'un autre prospect (ne doit pas apparaître)
        RentalApplication::factory()->create([
            'property_id' => $property->id,
            'agent_id'    => $agent->id,
        ]);

        $response = $this->withToken($token)->getJson('/api/prospect/applications');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_prospect_peut_voir_ses_contrats(): void
    {
        [$agent]         = $this->makeAgent();
        [$bailleur]      = $this->makeBailleur();
        [$client, $token] = $this->makeClient();
        $property        = $this->makeProperty($bailleur, $agent);

        Rental::factory()->create([
            'property_id' => $property->id,
            'tenant_id'   => $client->id,
            'agent_id'    => $agent->id,
            'status'      => 'pending',
        ]);
        // Contrat d'un autre locataire
        Rental::factory()->create([
            'property_id' => $property->id,
            'agent_id'    => $agent->id,
            'status'      => 'active',
        ]);

        $response = $this->withToken($token)->getJson('/api/prospect/rentals');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // B. BailleurController — LECTURE SEULE vérifiée
    // ═══════════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_bailleur_ne_peut_plus_acceder_a_la_route_applications(): void
    {
        [, $token] = $this->makeBailleur();

        // Cette route a été supprimée — doit retourner 404
        $this->withToken($token)
            ->getJson('/api/bailleur/applications')
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_bailleur_ne_peut_plus_modifier_le_statut_dune_candidature(): void
    {
        [, $token] = $this->makeBailleur();

        $this->withToken($token)
            ->postJson('/api/bailleur/applications/1/status', ['status' => 'active'])
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_bailleur_ne_peut_plus_modifier_le_statut_dune_visite(): void
    {
        [, $token] = $this->makeBailleur();

        $this->withToken($token)
            ->postJson('/api/bailleur/visits/1/status', ['status' => 'confirmed'])
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_bailleur_peut_voir_le_statut_locatif_de_son_bien_en_lecture_seule(): void
    {
        [$agent]           = $this->makeAgent();
        [$bailleur, $token] = $this->makeBailleur();
        $property          = $this->makeProperty($bailleur, $agent);

        $response = $this->withToken($token)
            ->getJson("/api/bailleur/properties/{$property->id}/rental-status");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'property' => ['id', 'title', 'status'],
                    'current_phase',
                    'phase_label',
                    'progress_percent',
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_bailleur_ne_peut_pas_voir_le_statut_du_bien_dun_autre_bailleur(): void
    {
        [$agent]            = $this->makeAgent();
        [$bailleur1]        = $this->makeBailleur();
        [, $token2]         = $this->makeBailleur();
        $property           = $this->makeProperty($bailleur1, $agent);

        $this->withToken($token2)
            ->getJson("/api/bailleur/properties/{$property->id}/rental-status")
            ->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_statut_locatif_indique_la_bonne_phase(): void
    {
        [$agent]           = $this->makeAgent();
        [$bailleur, $token] = $this->makeBailleur();
        $property          = $this->makeProperty($bailleur, $agent);
        $client            = User::factory()->create(['role' => 'client', 'roles' => ['client']]);

        // Créer une visite completed → phase 2 (25%)
        $this->makeCompletedVisit($property, $client, $agent);

        $response = $this->withToken($token)
            ->getJson("/api/bailleur/properties/{$property->id}/rental-status");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.current_phase'));
        $this->assertEquals(25, $response->json('data.progress_percent'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_statut_locatif_indique_phase_3_quand_location_active(): void
    {
        [$agent]           = $this->makeAgent();
        [$bailleur, $token] = $this->makeBailleur();
        $property          = $this->makeProperty($bailleur, $agent);
        $client            = User::factory()->create(['role' => 'locataire', 'roles' => ['locataire']]);

        Rental::factory()->create([
            'property_id' => $property->id,
            'tenant_id'   => $client->id,
            'agent_id'    => $agent->id,
            'status'      => 'active',
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/bailleur/properties/{$property->id}/rental-status");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.current_phase'));
        $this->assertEquals(100, $response->json('data.progress_percent'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_bailleur_voit_ses_visites_sans_identite_prospect(): void
    {
        [$agent]           = $this->makeAgent();
        [$bailleur, $token] = $this->makeBailleur();
        $property          = $this->makeProperty($bailleur, $agent);
        $client            = User::factory()->create(['role' => 'client', 'roles' => ['client']]);

        Visit::factory()->create([
            'property_id'        => $property->id,
            'user_id'            => $client->id,
            'agent_id'           => $agent->id,
            'status'             => 'pending',
            'fee_payment_status' => 'pending',
        ]);

        $response = $this->withToken($token)->getJson('/api/bailleur/visits');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $data = $response->json('data');
        $this->assertCount(1, $data);

        // Vérifier que l'identité du prospect n'est PAS exposée
        $this->assertArrayNotHasKey('user_id', $data[0]);
        $this->assertArrayNotHasKey('visitor', $data[0]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // C. Sécurité — Isolation par rôle
    // ═══════════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_invite_ne_peut_pas_acceder_aux_routes_prospect(): void
    {
        $this->getJson('/api/prospect/visits')->assertStatus(401);
        $this->postJson('/api/prospect/visits', [])->assertStatus(401);
        $this->getJson('/api/prospect/applications')->assertStatus(401);
        $this->postJson('/api/prospect/applications', [])->assertStatus(401);
        $this->getJson('/api/prospect/rentals')->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_invite_ne_peut_pas_acceder_aux_routes_bailleur(): void
    {
        $this->getJson('/api/bailleur/dashboard')->assertStatus(401);
        $this->getJson('/api/bailleur/visits')->assertStatus(401);
        $this->getJson('/api/bailleur/properties/1/rental-status')->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function scenario_complet_avec_roles_corrects(): void
    {
        // ── Acteurs ─────────────────────────────────────────────────
        [$agent]    = $this->makeAgent();
        [$bailleur] = $this->makeBailleur();
        [$prospect] = $this->makeClient();

        // ── Setup ────────────────────────────────────────────────────
        $property = $this->makeProperty($bailleur, $agent);

        // 1. Prospect réserve une visite
        \Laravel\Sanctum\Sanctum::actingAs($prospect);
        $visitRes = $this->postJson('/api/prospect/visits', [
            'property_id'  => $property->id,
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);
        $visitRes->assertStatus(201);
        $visitId = $visitRes->json('data.id');

        // Vérifier que l'agent_id est bien propagé depuis le bien
        $this->assertDatabaseHas('visits', [
            'id'       => $visitId,
            'agent_id' => $agent->id,
        ]);

        // 2. Bailleur ne peut PAS confirmer la visite (route supprimée)
        \Laravel\Sanctum\Sanctum::actingAs($bailleur);
        $this->postJson("/api/bailleur/visits/{$visitId}/status", ['status' => 'confirmed'])
            ->assertStatus(404);

        // 3. On simule le paiement des frais (en réel ce serait via payment gateway)
        Visit::where('id', $visitId)->update(['fee_payment_status' => 'paid']);

        // 4. Agent confirme → visite passe à "confirmed"
        \Laravel\Sanctum\Sanctum::actingAs($agent);
        $this->postJson("/api/agent/visits/{$visitId}/confirm")->assertStatus(200);

        $this->assertDatabaseHas('visits', ['id' => $visitId, 'status' => 'confirmed']);

        // 5. Prospect confirme → visite passe à "completed" (les deux ont confirmé)
        \Laravel\Sanctum\Sanctum::actingAs($prospect);
        $confirmRes = $this->postJson("/api/prospect/visits/{$visitId}/confirm");

        $confirmRes->assertStatus(200);
        $this->assertTrue($confirmRes->json('can_apply'));
        $this->assertDatabaseHas('visits', ['id' => $visitId, 'status' => 'completed']);

        // 6. Prospect soumet un dossier
        \Laravel\Sanctum\Sanctum::actingAs($prospect);
        $appRes = $this->postJson('/api/prospect/applications', [
            'visit_id'                  => $visitId,
            'situation_professionnelle' => 'cdi',
            'revenus_mensuels'          => 500000,
        ]);
        $appRes->assertStatus(201);
        $appId = $appRes->json('data.id');

        // 7. Bailleur voit phase = 2 — lecture seule
        \Laravel\Sanctum\Sanctum::actingAs($bailleur);
        $statusRes = $this->getJson("/api/bailleur/properties/{$property->id}/rental-status");
        $statusRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, $statusRes->json('data.current_phase'));

        // 8. Agent valide le dossier
        \Laravel\Sanctum\Sanctum::actingAs($agent);
        $this->postJson("/api/agent/applications/{$appId}/validate", [
            'advance_months' => 3,
            'start_date'     => now()->addDays(10)->format('Y-m-d'),
        ])->assertStatus(200);

        $rental = Rental::where('application_id', $appId)->firstOrFail();

        // 9. Agent confirme le paiement → location active + rôle locataire
        \Laravel\Sanctum\Sanctum::actingAs($agent);
        $this->postJson("/api/agent/rentals/{$rental->id}/confirm-payment", [
            'payment_method' => 'momo',
        ])->assertStatus(200);

        // 10. Vérifications finales
        $this->assertDatabaseHas('rentals', ['id' => $rental->id, 'status' => 'active']);
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'status' => 'rented']);
        $prospect->refresh();
        $this->assertEquals('locataire', $prospect->role);
        $this->assertTrue($prospect->hasRole('locataire'));

        // 11. Bailleur voit phase = 3 à 100% — lecture seule
        \Laravel\Sanctum\Sanctum::actingAs($bailleur);
        $finalStatus = $this->getJson("/api/bailleur/properties/{$property->id}/rental-status");
        $finalStatus->assertStatus(200);
        $this->assertEquals(3, $finalStatus->json('data.current_phase'));
        $this->assertEquals(100, $finalStatus->json('data.progress_percent'));
    }
}
