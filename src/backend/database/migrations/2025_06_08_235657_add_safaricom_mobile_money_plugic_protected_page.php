<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        DB::table('protected_pages')->insert([
            'name' => '/safaricom-mobile-money-overview',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        DB::table('protected_pages')
            ->where('name', '/safaricom-mobile-money-overview')
            ->delete();
    }
};
