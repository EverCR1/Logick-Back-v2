<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('municipio', 100)->nullable()->after('direccion');
            $table->string('departamento', 100)->nullable()->after('municipio');
            $table->string('referencia', 255)->nullable()->after('departamento');
            $table->string('horario', 150)->nullable()->after('referencia');
            $table->decimal('lat', 10, 7)->nullable()->after('horario');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['municipio', 'departamento', 'referencia', 'horario', 'lat', 'lng']);
        });
    }
};