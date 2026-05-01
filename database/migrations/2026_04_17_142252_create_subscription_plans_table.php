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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            // type de facturation
            $table->enum('billing_type', ['one_time', 'monthly', 'hybrid']);

            // prix principal (mensuel ou global)
            $table->integer('price')->nullable();

            // durée
            $table->integer('duration_months')->nullable();

            // type étudiant
            $table->boolean('is_student_plan')->default(false);

            // frais d’inscription
            $table->integer('registration_fee')->nullable();

            // montant mensualité (si "monthly")
            $table->integer('monthly_price')->nullable();

            // nombre de mensualités
            $table->integer('total_payments')->nullable();

            // avantages (FRONT)
            $table->json('advantages')->nullable();

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
        Schema::dropIfExists('subscription_plans');
    }
};
