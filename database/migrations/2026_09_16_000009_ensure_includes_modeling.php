<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maker_job_types')) {
            return;
        }

        if (! Schema::hasColumn('maker_job_types', 'includes_modeling')) {
            Schema::table('maker_job_types', function (Blueprint $table) {
                $table->boolean('includes_modeling')->default(false)->after('is_design_only');
            });
        }

        $modelingSlugs = ['modeling_3d', 'full_package', 'character_figure', 'working_prototype'];
        DB::table('maker_job_types')->whereIn('slug', $modelingSlugs)->update(['includes_modeling' => 1]);
        DB::table('maker_job_types')->whereIn('slug', ['print_3d', 'design_mockup'])->update(['includes_modeling' => 0]);

        // Custom rows (not seed slugs): infer from name/slug so the new
        // default-false column does not hide extensions for modeling types.
        foreach (DB::table('maker_job_types')->get() as $row) {
            $hay = (string) ($row->slug ?? '').' '.(string) ($row->name ?? '');
            if (preg_match('/print_3d|출력\s*대행|design_mockup|목업/u', $hay)) {
                DB::table('maker_job_types')->where('id', $row->id)->update(['includes_modeling' => 0]);
                continue;
            }
            if (preg_match('/modeling|모델링|full_package|풀\s*패키지|character_figure|커미션|working_prototype|워킹\s*프로토타입/u', $hay)) {
                DB::table('maker_job_types')->where('id', $row->id)->update(['includes_modeling' => 1]);
            }
        }
    }

    public function down(): void
    {
        // Additive 0.5.3 safety net. Do not drop.
    }
};
