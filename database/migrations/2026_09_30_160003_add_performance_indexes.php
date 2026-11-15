<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Dette #08 : Table jobs pour QUEUE_CONNECTION=database
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        // Dette #06 & #24 : S'assurer que publish_at existe sur subsections
        if (Schema::hasTable('subsections') && !Schema::hasColumn('subsections', 'publish_at')) {
            Schema::table('subsections', function (Blueprint $table) {
                $table->timestamp('publish_at')->nullable()->after('order');
            });
        }

        // Dette #21 : Champ explicite is_visible sur sections (remplace le magic number order !== 7)
        if (Schema::hasTable('sections') && !Schema::hasColumn('sections', 'is_visible')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->boolean('is_visible')->default(true)->after('order');
            });

            if (Schema::hasTable('pages')) {
                $accueilIds = DB::table('pages')->where('slug', 'accueil')->pluck('id');
                if ($accueilIds->isNotEmpty()) {
                    DB::table('sections')
                        ->whereIn('page_id', $accueilIds)
                        ->where('order', 7)
                        ->update(['is_visible' => false]);
                }
            }
        }

        // Index composite pour la relation activeSubscription()
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['adherent_id', 'status'], 'idx_subs_adherent_status');
            });
        }

        // Index composite pour les agrégations de statistiques
        if (Schema::hasTable('daily_visits')) {
            Schema::table('daily_visits', function (Blueprint $table) {
                $table->index(['date', 'count'], 'idx_daily_visits_date_count');
            });
        }

        // Index pour le dashboard et réconciliation des dons
        if (Schema::hasTable('tsedakas')) {
            Schema::table('tsedakas', function (Blueprint $table) {
                $table->index(['created_at', 'montant'], 'idx_tsedakas_created_montant');
            });
        }

        // Dette #24 : Index composite sur orders
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_orders_status_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('idx_orders_status_created');
            });
        }

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex('idx_subs_adherent_status');
            });
        }

        if (Schema::hasTable('daily_visits')) {
            Schema::table('daily_visits', function (Blueprint $table) {
                $table->dropIndex('idx_daily_visits_date_count');
            });
        }

        if (Schema::hasTable('tsedakas')) {
            Schema::table('tsedakas', function (Blueprint $table) {
                $table->dropIndex('idx_tsedakas_created_montant');
            });
        }

        if (Schema::hasTable('sections') && Schema::hasColumn('sections', 'is_visible')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropColumn('is_visible');
            });
        }

        if (Schema::hasTable('subsections') && Schema::hasColumn('subsections', 'publish_at')) {
            Schema::table('subsections', function (Blueprint $table) {
                $table->dropColumn('publish_at');
            });
        }

        Schema::dropIfExists('jobs');
    }
};
