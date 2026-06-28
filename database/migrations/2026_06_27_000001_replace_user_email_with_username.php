<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'email')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $row) {
            $base = strtolower((string) str($row->email)->before('@'));
            $username = $base !== '' ? $base : 'user' . $row->id;

            $candidate = $username;
            $suffix = 1;
            while (
                DB::table('users')
                    ->where('username', $candidate)
                    ->where('id', '!=', $row->id)
                    ->exists()
            ) {
                $candidate = $username . $suffix;
                $suffix++;
            }

            DB::table('users')->where('id', $row->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }
            $table->dropUnique(['email']);
            $table->dropColumn('email');
            $table->unique('username');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->timestamp('email_verified_at')->nullable();
        });

        foreach (DB::table('users')->orderBy('id')->get(['id', 'username']) as $row) {
            DB::table('users')
                ->where('id', $row->id)
                ->update(['email' => ($row->username ?: 'user' . $row->id) . '@clinic.com']);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->unique('email');
        });
    }
};
