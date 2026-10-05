<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Table of laravel/passkeys, written by the project instead of publishing the
| migration of the package (D7). credential_id is the base64url id of the
| credential, up to 1023 bytes by the WebAuthn specification, so up to 1364
| characters: in ascii they are 1364 bytes, below the 3072 bytes of an InnoDB
| index. The binary collation keeps the case, which base64url needs.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id', 1364)->charset('ascii')->collation('ascii_bin')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
