<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nom_entreprise')->nullable();
            $table->string('logo')->nullable();
            $table->string('telephone')->nullable();
            $table->text('adresse')->nullable();
            $table->string('devise')->default('XOF');
            $table->string('signature')->nullable();
            $table->string('tampon')->nullable();
            $table->string('pin_code')->nullable();
            $table->integer('auto_lock_time')->default(5);
            $table->integer('quota_proprietes')->default(10);
            $table->integer('quota_locataires')->default(50);
            $table->enum('niveau_abonnement', ['decouverte', 'intermediaire', 'avance', 'entreprise'])->default('decouverte');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('configurations');
    }
};