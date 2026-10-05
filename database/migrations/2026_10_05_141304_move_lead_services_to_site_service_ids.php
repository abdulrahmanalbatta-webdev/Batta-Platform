<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A lead's service is now one of the site's services (محتوى الموقع ← الخدمات), stored by its id.
     * The old fixed list used two ids the site writes differently.
     */
    private const RENAMED = ['web_apps' => 'web-apps', 'stores' => 'ecommerce'];

    public function up(): void
    {
        foreach (self::RENAMED as $old => $new) {
            DB::table('leads')->where('service', $old)->update(['service' => $new]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::RENAMED as $old => $new) {
            DB::table('leads')->where('service', $new)->update(['service' => $old]);
        }
    }
};
