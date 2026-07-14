<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('transactions_mobile', function (Blueprint $table) {
        $table->id();
        $table->foreignId('contrat_id')->constrained()->onDelete('cascade');
        $table->foreignId('locataire_id')->constrained()->onDelete('cascade');
        $table->foreignId('paiement_id')->nullable()->constrained()->onDelete('set null');
        $table->string('reference')->unique();
        $table->string('transaction_id')->nullable();
        $table->decimal('montant', 15, 2);
        $table->string('operateur');
        $table->string('numero_telephone');
        $table->integer('periode_mois');
        $table->integer('periode_annee');
        $table->enum('statut', ['en_attente', 'en_cours', 'confirme', 'echoue'])->default('en_attente');
        $table->timestamp('date_initiation');
        $table->timestamp('date_confirmation')->nullable();
        $table->text('message_erreur')->nullable();
        $table->timestamps();
        
        $table->index(['reference', 'statut']);
        $table->index(['operateur', 'statut']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions_mobile');
    }
};
