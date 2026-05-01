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
        Schema::create('adherent_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('adherent_id')->constrained()->cascadeOnDelete();

            // 📍 Infos perso
            $table->string('adresse')->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('situation_matrimoniale')->nullable();
            $table->integer('nombre_enfants')->nullable();
            $table->string('profession')->nullable();

            // 📞 Contacts
            $table->string('telephone_whatsapp')->nullable();
            $table->string('telephone_secondaire')->nullable();

            // 🚨 Urgence
            $table->string('urgence_nom')->nullable();
            $table->string('urgence_numero')->nullable();
            $table->string('urgence_lien')->nullable();

            // 🎓 Education
            $table->string('niveau_etudes')->nullable();
            $table->string('dernier_diplome')->nullable();

            // 📖 Religieux
            $table->boolean('etude_religieuse')->default(false);
            $table->string('institution_religieuse')->nullable();
            $table->string('niveau_juif')->nullable();

            // 🌍 Langues
            $table->string('niveau_francais')->nullable();
            $table->string('niveau_hebreu')->nullable();
            $table->string('autres_langues')->nullable();

            // 🎯 Motivation
            $table->text('motivation')->nullable();
            $table->text('objectifs')->nullable();

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
        Schema::dropIfExists('adherent_profiles');
    }
};
