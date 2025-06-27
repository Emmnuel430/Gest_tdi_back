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
        Schema::table('adherents', function (Blueprint $table) {
            $table->enum('abonnement_type', ['hebdomadaire', 'mensuel', 'annuel'])->nullable()->after('statut');
            $table->date('abonnement_expires_at')->nullable()->after('abonnement_type');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('adherents', function (Blueprint $table) {
            //
        });
    }
};
