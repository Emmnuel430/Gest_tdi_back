<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tsedakas', function (Blueprint $table) {
            $table->id();

            $table->string('reference')->unique();

            // infos donateur
            $table->string('nom')->nullable();
            $table->string('prenom')->nullable();
            $table->string('email');

            // don
            $table->integer('montant');
            $table->boolean('anonymous')->default(false);

            // optionnel
            $table->text('message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tsedakas');
    }
};
