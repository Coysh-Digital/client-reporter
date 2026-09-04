<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->nullable();      // openai | anthropic | ollama
            $table->string('model')->nullable();
            $table->string('base_url')->nullable();
            $table->boolean('enabled')->default(false);
            // Encrypted at the application layer (see AiSetting::casts()); holds
            // ['api_key' => ...]. Never stored in the plaintext settings table.
            $table->text('credentials')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
