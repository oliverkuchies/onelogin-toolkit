<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOneLoginSiteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('onelogin_site', function (Blueprint $table) {
            $table->id();
            $table->string('site_name');

            $table->string('sp_entity_id');
            $table->string('sp_acs_url');
            $table->string('sp_acs_binding');
            $table->string('sp_slo_url');
            $table->string('sp_slo_binding');
            $table->string('sp_name_id_format');
            $table->string('sp_x509cert');
            $table->string('sp_private_key')->nullable();

            $table->string('ip_entity_id');
            $table->string('ip_acs_url');
            $table->string('ip_acs_binding');
            $table->string('ip_slo_url');
            $table->string('ip_slo_binding');
            $table->string('ip_name_id_format');
            $table->string('ip_x509cert')->nullable();
            $table->string('ip_private_key')->nullable();

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
        Schema::dropIfExists('onelogin_site');
    }
}
