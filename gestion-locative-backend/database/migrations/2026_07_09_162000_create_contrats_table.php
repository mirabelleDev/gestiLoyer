<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locataire_id')->constrained('locataires')->cascadeonDelete();
            $table->foreignId('unite_locative_id')->constrained('unites_locatives')->cascadeOnDelete();            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->integer('duree_mois')->default(12);
            $table->decimal('loyer_mensuel', 15, 2);
            $table->string('devise')->default('XOF');
            $table->decimal('caution', 15, 2)->default(0);
            $table->decimal('avance_loyer', 15, 2)->default(0);
            $table->enum('statut', ['actif', 'suspendu', 'resilie', 'termine'])->default('actif');
            $table->date('date_resiliation')->nullable();
            $table->text('motif_resiliation')->nullable();
            $table->text('conditions_speciales')->nullable();
            $table->string('document_pdf')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contrats');
    }
};