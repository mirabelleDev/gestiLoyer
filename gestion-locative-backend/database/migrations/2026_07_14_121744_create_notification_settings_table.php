<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('email_notifications')->default(true);
            $table->boolean('sms_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->boolean('retard_paiement')->default(true);
            $table->boolean('contrat_expiration')->default(true);
            $table->boolean('nouvelle_demande')->default(true);
            $table->boolean('preavis_recu')->default(true);
            $table->integer('retard_alerte_jours')->default(5);
            $table->integer('contrat_rappel_jours')->default(30);
            $table->time('notification_heure')->default('08:00:00');
            $table->json('preferences')->nullable();
            $table->timestamps();
        });
    }
};

   