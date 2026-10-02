<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->json('settings')->nullable();      // JsonEditor->asArray()
            $table->text('settings_text')->nullable(); // JsonEditor, kept as text
            $table->text('config')->nullable();        // YamlEditor, kept as text
            $table->json('config_data')->nullable();   // YamlEditor->asArray()
            $table->json('meta')->nullable();          // AsCollection, YamlEditor without asArray (follows the cast)
            $table->json('options')->nullable();       // `object` cast, YamlEditor->asArray()
            $table->json('extras')->nullable();        // `array` cast, JsonEditor without asArray (follows the cast)
            $table->json('strict')->nullable();        // `array` cast, JsonEditor->asArray()->validateSyntax(false)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('users');
    }
};
