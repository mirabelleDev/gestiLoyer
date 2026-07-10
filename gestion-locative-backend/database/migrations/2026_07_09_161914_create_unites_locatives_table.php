<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('unites_locatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propriete_id')->constrained()->onDelete('cascade');
            $table->string('numero');
            $table->string('etage')->nullable();
            $table->enum('type', ['appartement', 'chambre', 'boutique', 'bureau', 'autre'])->default('appartement');
            $table->decimal('superficie', 10, 2)->nullable();
            $table->decimal('loyer_nominal', 15, 2);
            $table->string('devise')->default('XOF');
            $table->enum('statut', ['disponible', 'occupe', 'travaux', 'reserve'])->default('disponible');
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('unites_locatives');
    }
};