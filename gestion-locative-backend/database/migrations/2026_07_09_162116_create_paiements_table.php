<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrat_id')->constrained()->onDelete('cascade');
            $table->foreignId('locataire_id')->constrained()->onDelete('cascade');
            $table->decimal('montant', 15, 2);
            $table->string('devise')->default('XOF');
            $table->integer('periode_mois');
            $table->integer('periode_annee');
            $table->enum('mode_paiement', ['especes', 'mobile_money', 'virement', 'cheque', 'autre'])->default('especes');
            $table->string('reference')->nullable();
            $table->enum('statut', ['complet', 'partiel', 'avance'])->default('complet');
            $table->decimal('reste_a_payer', 15, 2)->default(0);
            $table->dateTime('date_paiement');
            $table->string('numero_recu')->unique();
            $table->text('qr_code_data')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->string('recu_pdf')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['periode_mois', 'periode_annee']);
            $table->index('numero_recu');
        });
    }

    public function down()
    {
        Schema::dropIfExists('paiements');
    }
};