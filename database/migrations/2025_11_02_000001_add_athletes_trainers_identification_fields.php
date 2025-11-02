<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            $table->date('last_activity')->nullable();
            $table->string('telegram_chat_id')->nullable()->after('external_id');
            $table->string('tiiva_id')->nullable()->after('telegram_chat_id');
            $table->string('webling_id')->nullable()->after('tiiva_id');
            $table->string('nds_id')->nullable()->after('webling_id');
            $table->string('seltec_id')->nullable()->after('nds_id');
            $table->json('preferences')->nullable()->after('seltec_id');
        });

        Schema::table('trainers', function (Blueprint $table) {
            $table->date('last_activity')->nullable();
            $table->string('telegram_chat_id')->nullable()->after('external_id');
            $table->string('tiiva_id')->nullable()->after('telegram_chat_id');
            $table->string('webling_id')->nullable()->after('tiiva_id');
            $table->string('nds_id')->nullable()->after('webling_id');
            $table->string('seltec_id')->nullable()->after('nds_id');
            $table->json('preferences')->nullable()->after('seltec_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            $table->dropColumn('last_activity');
            $table->dropColumn('telegram_chat_id');
            $table->dropColumn('tiiva_id');
            $table->dropColumn('webling_id');
            $table->dropColumn('nds_id');
            $table->dropColumn('preferences');
        });

        Schema::table('trainers', function (Blueprint $table) {
            $table->dropColumn('last_activity');
            $table->dropColumn('telegram_chat_id');
            $table->dropColumn('tiiva_id');
            $table->dropColumn('webling_id');
            $table->dropColumn('nds_id');
            $table->dropColumn('preferences');
        });
    }
};
