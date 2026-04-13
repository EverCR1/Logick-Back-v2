<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cupones', function (Blueprint $table) {
            $table->string('mensaje_error')->nullable()->after('estado')
                  ->comment('Mensaje personalizado cuando el cupón no aplica. Si null, se usa el mensaje genérico.');
        });
    }

    public function down(): void
    {
        Schema::table('cupones', function (Blueprint $table) {
            $table->dropColumn('mensaje_error');
        });
    }
};