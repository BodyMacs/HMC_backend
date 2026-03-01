<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor du processus locatif HomeCameroon.
 *
 * Modifications :
 * 1. properties  → ajout de agent_id (agent responsable du bien)
 * 2. visits      → ajout de agent_id, confirmed_by_user, confirmed_by_agent, visit_fee, payment_status
 * 3. rentals     → ajout de agent_id, advance_months, caution_amount (contrat final uniquement)
 * 4. NEW TABLE : rental_applications → dossier de candidature (entre visite et contrat final)
 * 5. transactions → ajout rental_id, rental_application_id, confirmed_by_agent, confirmed_by (pour les paiements locatifs)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Properties : ajouter agent_id ─────────────────────────────────
        Schema::table('properties', function (Blueprint $table): void {
            // L'agent HMC qui a publié et gère ce bien physiquement
            $table->foreignId('agent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // ─── 2. Visits : enrichir pour le nouveau processus ───────────────────
        Schema::table('visits', function (Blueprint $table): void {
            // Agent qui accompagne la visite
            $table->foreignId('agent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            // Double confirmation (les deux parties doivent confirmer)
            $table->boolean('confirmed_by_user')->default(false)->after('status');
            $table->boolean('confirmed_by_agent')->default(false)->after('confirmed_by_user');

            // Frais de visite
            $table->decimal('visit_fee', 12, 2)->default(15000)->after('confirmed_by_agent');
            $table->enum('fee_payment_status', ['pending', 'paid', 'waived'])->default('pending')->after('visit_fee');
            $table->enum('fee_payment_method', ['momo', 'om', 'card', 'cash'])->nullable()->after('fee_payment_status');

            // Date effective de la visite (peut différer de scheduled_at)
            $table->dateTime('visited_at')->nullable()->after('scheduled_at');
        });

        // ─── 3. NEW TABLE : rental_applications (Dossier de candidature) ──────
        Schema::create('rental_applications', function (Blueprint $table): void {
            $table->id();

            // Relations
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');     // Futur locataire
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete(); // Visite qui a précédé
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();  // Agent HMC

            // Informations du dossier
            $table->string('situation_professionnelle')->nullable(); // cdi, cdd, etudiant, independant...
            $table->decimal('revenus_mensuels', 12, 2)->nullable();
            $table->boolean('has_garant')->default(false);
            $table->text('notes')->nullable();

            // Statut du dossier
            $table->enum('status', ['pending', 'under_review', 'validated', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); // Agent qui a validé

            // Documents (liste des fichiers uploadés - stockés dans une autre table ou JSON)
            $table->json('documents')->nullable(); // [{type: 'cin_recto', path: '...', uploaded_at: '...'}]

            // Contrat de bail
            $table->text('contract_path')->nullable();         // Chemin du PDF généré
            $table->boolean('signed_by_applicant')->default(false);
            $table->timestamp('signed_at')->nullable();

            $table->timestamps();
            $table->unique(['property_id', 'user_id', 'visit_id']); // une seule candidature par visite
        });

        // ─── 4. Rentals : enrichir pour le contrat final ──────────────────────
        Schema::table('rentals', function (Blueprint $table): void {
            // Lier au dossier validé
            $table->foreignId('application_id')
                ->nullable()
                ->after('property_id')
                ->constrained('rental_applications')
                ->nullOnDelete();

            // Agent HMC responsable
            $table->foreignId('agent_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('users')
                ->nullOnDelete();

            // Détails financiers du contrat
            $table->integer('advance_months')->default(1)->after('price');          // nb mois d'avance
            $table->decimal('caution_amount', 12, 2)->nullable()->after('advance_months'); // caution
            $table->decimal('advance_amount', 12, 2)->nullable()->after('caution_amount'); // total avance

            // Phase de paiement initial
            $table->enum('payment_phase_status', ['pending', 'paid', 'confirmed'])->default('pending')->after('payment_status');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_phase_status');
            $table->foreignId('payment_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // ─── 5. Transactions : lier aux nouveaux objets ───────────────────────
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('rental_id')
                ->nullable()
                ->after('user_id')
                ->constrained('rentals')
                ->nullOnDelete();

            $table->foreignId('rental_application_id')
                ->nullable()
                ->after('rental_id')
                ->constrained('rental_applications')
                ->nullOnDelete();

            // L'agent qui confirme la réception physique du paiement
            $table->foreignId('confirmed_by_agent_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('agent_confirmed_at')->nullable()->after('confirmed_by_agent_id');

            // Type plus précis pour le contexte immobilier
            $table->string('rental_payment_type')->nullable()->after('type'); // visit_fee | caution | avance | loyer
        });
    }

    public function down(): void
    {
        // Dans l'ordre inverse des contraintes

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_by_agent_id');
            $table->dropConstrainedForeignId('rental_application_id');
            $table->dropConstrainedForeignId('rental_id');
            $table->dropColumn(['agent_confirmed_at', 'rental_payment_type']);
        });

        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_confirmed_by');
            $table->dropConstrainedForeignId('agent_id');
            $table->dropConstrainedForeignId('application_id');
            $table->dropColumn(['advance_months', 'caution_amount', 'advance_amount', 'payment_phase_status', 'payment_confirmed_at']);
        });

        Schema::dropIfExists('rental_applications');

        Schema::table('visits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn(['confirmed_by_user', 'confirmed_by_agent', 'visit_fee', 'fee_payment_status', 'fee_payment_method', 'visited_at']);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('agent_id');
        });
    }
};
