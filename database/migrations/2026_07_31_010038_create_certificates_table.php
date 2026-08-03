<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->string('code', 32)->unique();
            $table->timestamp('issued_at');
            $table->string('pdf_path')->nullable();
            $table->string('template_hash', 64)->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->string('email_status', 20)->default('pending');
            $table->text('email_error')->nullable();
            $table->timestamp('first_downloaded_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'participant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
