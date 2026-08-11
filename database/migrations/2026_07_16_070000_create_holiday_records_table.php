<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 마이그레이션을 실행한다.
     */
    public function up(): void
    {
        Schema::create('holiday_records', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * 마이그레이션을 되돌린다.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_records');
    }
};
