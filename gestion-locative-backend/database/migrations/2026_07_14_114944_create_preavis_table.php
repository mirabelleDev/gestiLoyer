// database/migrations/xxxx_xx_xx_create_preavis_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('preavis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrat_id')->constrained()->onDelete('cascade');
            $table->foreignId('locataire_id')->constrained()->onDelete('cascade');
            $table->date('date_depart');
            $table->text('motif');
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente');
            $table->timestamp('date_approbation')->nullable();
            $table->text('motif_rejet')->nullable();
            $table->text('commentaire')->nullable();
            $table->boolean('notifie_proprietaire')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['contrat_id', 'statut']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('preavis');
    }
};