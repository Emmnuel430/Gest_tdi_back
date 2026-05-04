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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('adherent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained();

            $table->enum('status', ['active', 'suspended', 'expired', 'completed']);

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();

            $table->integer('remaining_months')->nullable();

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
        Schema::dropIfExists('subscriptions');
    }
};
