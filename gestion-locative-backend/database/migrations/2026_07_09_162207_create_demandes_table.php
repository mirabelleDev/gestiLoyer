<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locataire_id')->constrained()->onDelete('cascade');
            $table->foreignId('unite_locative_id')->constrained('unites_locatives')->cascadeOnDelete();            
            $table->string('titre');
            $table->text('description');
            $table->enum('type', ['reparation', 'entretien', 'plainte', 'autre'])->default('reparation');
            $table->enum('statut', ['en_attente', 'en_cours', 'resolu', 'rejete'])->default('en_attente');
            $table->dateTime('date_resolution')->nullable();
            $table->text('commentaire_resolution')->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('demandes');
    }
};