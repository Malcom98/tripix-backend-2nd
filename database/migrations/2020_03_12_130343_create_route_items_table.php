<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRouteItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('route_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('route_id');
            $table->string('place_reference');
            $table->unsignedInteger('order');
            $table->time('time');
            $table->double('distance');
            $table->unsignedInteger('transport_type_id');
            $table->timestamps();

            $table->foreign('route_id')->references('id')->on('routes');
            $table->foreign('transport_type_id')->references('id')->on('transport_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table->dropForeign('route_items_route_id_foreign');
        $table->dropForeign('route_items_transport_type_id_foreign');
        Schema::dropIfExists('route_items');
    }
}
