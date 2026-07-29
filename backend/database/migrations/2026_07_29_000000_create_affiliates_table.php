<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table): void {
            $table->id();
            $table->string('folio')->nullable()->unique();
            $table->date('application_date');
            $table->string('first_name');
            $table->string('paternal_last_name');
            $table->string('maternal_last_name')->nullable();
            $table->char('curp', 18)->unique();
            $table->date('birth_date');
            $table->string('address_street');
            $table->string('neighborhood');
            $table->string('locality');
            $table->string('municipality');
            $table->string('state');
            $table->char('postal_code', 5);
            $table->string('home_phone')->nullable();
            $table->string('mobile_phone');
            $table->string('email');
            $table->string('occupation');
            $table->string('livestock_association')->nullable();
            $table->string('oje_v_branch');
            $table->string('profile_photo_path')->nullable();
            $table->string('ine_front_path');
            $table->string('ine_back_path');
            $table->string('signature_name');
            $table->timestamp('consent_accepted_at');
            $table->string('status')->default('submitted');
            $table->json('ocr_metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'application_date']);
            $table->index(['paternal_last_name', 'maternal_last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
