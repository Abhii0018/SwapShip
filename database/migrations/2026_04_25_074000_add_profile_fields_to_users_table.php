<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('address');
            $table->unsignedTinyInteger('age')->nullable()->after('profile_photo_path');
            $table->string('gender', 20)->nullable()->after('age');
            $table->string('city')->nullable()->after('gender');
            $table->string('state')->nullable()->after('city');
            $table->string('pincode', 20)->nullable()->after('state');
            $table->string('location')->nullable()->after('pincode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_photo_path',
                'age',
                'gender',
                'city',
                'state',
                'pincode',
                'location',
            ]);
        });
    }
};
