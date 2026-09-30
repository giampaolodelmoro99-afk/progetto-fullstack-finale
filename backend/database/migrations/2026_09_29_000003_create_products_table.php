<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 255);
            $table->string('slug')->unique();
            $table->string('sku', 64)->unique();
            $table->string('short_description', 160);
            $table->text('description')->nullable();
            $table->json('specifications');
            $table->unsignedInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('created_at');
            $table->index('price');
            $table->index('stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
