<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('proprietes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('adresse');
            $table->string('ville')->nullable();
            $table->string('quartier')->nullable();
            $table->string('code_postal')->nullable();
            $table->enum('type', ['immeuble', 'villa', 'residence', 'autre'])->default('immeuble');
            $table->integer('nombre_unites')->default(0);
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->foreignId('gerant_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('proprietaire_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('proprietes');
    }
};