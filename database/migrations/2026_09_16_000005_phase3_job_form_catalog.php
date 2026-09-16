<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createTypesTable();
        $this->seedDefaultTypes();
        $this->createFilesTable();
        $this->extendJobsTable();
        $this->migrateLegacyJobs();
    }

    public function down(): void
    {
        if (Schema::hasTable('maker_jobs')) {
            $drop = [
                'type_id', 'budget_min', 'budget_max', 'rush_fee_enabled', 'rush_deadline',
                'schedule_premium_enabled', 'size_w', 'size_d', 'size_h', 'provided_extensions',
                'revision_enabled', 'revision_count', 'revision_cost', 'contact_name', 'contact_phone',
                'contact_hours', 'contact_email', 'zipcode', 'address', 'address_detail', 'upload_token',
            ];
            Schema::table('maker_jobs', function (Blueprint $table) use ($drop) {
                foreach ($drop as $column) {
                    if (Schema::hasColumn('maker_jobs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('maker_job_files');
        Schema::dropIfExists('maker_job_types');
    }

    private function createTypesTable(): void
    {
        if (Schema::hasTable('maker_job_types')) {
            return;
        }

        Schema::create('maker_job_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('requires_address')->default(true);
            $table->boolean('is_design_only')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_seeded')->default(false);
            $table->timestamps();
        });
    }

    private function seedDefaultTypes(): void
    {
        if (! Schema::hasTable('maker_job_types')) {
            return;
        }

        $now = now();
        $defaults = [
            ['modeling_3d', '3D 모델링', '3D 모델링 의뢰', 0, 1, 10],
            ['print_3d', '3D 출력 대행', '3D 출력 대행 의뢰', 1, 0, 20],
            ['full_package', '풀 패키지 제작 (모델링 + 출력 + 후가공)', '모델링, 출력, 후가공을 포함한 풀 패키지', 1, 0, 30],
            ['character_figure', '캐릭터·피규어 커미션', '캐릭터 및 피규어 커미션', 1, 0, 40],
            ['design_mockup', '디자인 목업(Mock-up) 및 시제품', '디자인 목업 및 시제품 (순수 디자인류)', 0, 1, 50],
            ['working_prototype', '워킹 프로토타입(기능성 시제품)', '기능성 워킹 프로토타입', 1, 0, 60],
        ];

        foreach ($defaults as [$slug, $name, $description, $requiresAddress, $designOnly, $sort]) {
            $exists = DB::table('maker_job_types')->where('slug', $slug)->exists();
            if ($exists) {
                continue;
            }
            DB::table('maker_job_types')->insert([
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'requires_address' => $requiresAddress,
                'is_design_only' => $designOnly,
                'is_enabled' => 1,
                'sort_order' => $sort,
                'is_seeded' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function createFilesTable(): void
    {
        if (Schema::hasTable('maker_job_files')) {
            return;
        }

        Schema::create('maker_job_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('upload_token', 64)->nullable()->index();
            $table->string('collection', 32);
            $table->string('disk', 32)->default('public');
            $table->string('path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('hash', 24)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    private function extendJobsTable(): void
    {
        if (! Schema::hasTable('maker_jobs')) {
            return;
        }

        Schema::table('maker_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('maker_jobs', 'type_id')) {
                $table->unsignedBigInteger('type_id')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('maker_jobs', 'budget_min')) {
                $table->unsignedInteger('budget_min')->nullable()->after('budget');
            }
            if (! Schema::hasColumn('maker_jobs', 'budget_max')) {
                $table->unsignedInteger('budget_max')->nullable()->after('budget_min');
            }
            if (! Schema::hasColumn('maker_jobs', 'rush_fee_enabled')) {
                $table->boolean('rush_fee_enabled')->default(false)->after('closes_at');
            }
            if (! Schema::hasColumn('maker_jobs', 'rush_deadline')) {
                $table->timestamp('rush_deadline')->nullable()->after('rush_fee_enabled');
            }
            if (! Schema::hasColumn('maker_jobs', 'schedule_premium_enabled')) {
                $table->boolean('schedule_premium_enabled')->default(false)->after('rush_deadline');
            }
            if (! Schema::hasColumn('maker_jobs', 'size_w')) {
                $table->unsignedInteger('size_w')->nullable()->after('schedule_premium_enabled');
            }
            if (! Schema::hasColumn('maker_jobs', 'size_d')) {
                $table->unsignedInteger('size_d')->nullable()->after('size_w');
            }
            if (! Schema::hasColumn('maker_jobs', 'size_h')) {
                $table->unsignedInteger('size_h')->nullable()->after('size_d');
            }
            if (! Schema::hasColumn('maker_jobs', 'provided_extensions')) {
                $table->json('provided_extensions')->nullable()->after('size_h');
            }
            if (! Schema::hasColumn('maker_jobs', 'revision_enabled')) {
                $table->boolean('revision_enabled')->default(false)->after('provided_extensions');
            }
            if (! Schema::hasColumn('maker_jobs', 'revision_count')) {
                $table->unsignedTinyInteger('revision_count')->nullable()->after('revision_enabled');
            }
            if (! Schema::hasColumn('maker_jobs', 'revision_cost')) {
                $table->unsignedInteger('revision_cost')->nullable()->after('revision_count');
            }
            if (! Schema::hasColumn('maker_jobs', 'contact_name')) {
                $table->string('contact_name', 120)->nullable()->after('revision_cost');
            }
            if (! Schema::hasColumn('maker_jobs', 'contact_phone')) {
                $table->string('contact_phone', 40)->nullable()->after('contact_name');
            }
            if (! Schema::hasColumn('maker_jobs', 'contact_hours')) {
                $table->string('contact_hours', 40)->nullable()->after('contact_phone');
            }
            if (! Schema::hasColumn('maker_jobs', 'contact_email')) {
                $table->string('contact_email', 120)->nullable()->after('contact_hours');
            }
            if (! Schema::hasColumn('maker_jobs', 'zipcode')) {
                $table->string('zipcode', 12)->nullable()->after('contact_email');
            }
            if (! Schema::hasColumn('maker_jobs', 'address')) {
                $table->string('address', 255)->nullable()->after('zipcode');
            }
            if (! Schema::hasColumn('maker_jobs', 'address_detail')) {
                $table->string('address_detail', 255)->nullable()->after('address');
            }
            if (! Schema::hasColumn('maker_jobs', 'upload_token')) {
                $table->string('upload_token', 64)->nullable()->index()->after('address_detail');
            }
        });

        try {
            Schema::table('maker_jobs', function (Blueprint $table) {
                $table->index('type_id');
            });
        } catch (\Throwable) {
        }
    }

    private function migrateLegacyJobs(): void
    {
        if (! Schema::hasTable('maker_jobs') || ! Schema::hasTable('maker_job_types')) {
            return;
        }

        $map = [
            'print_3d' => 'print_3d',
            'design' => 'design_mockup',
            'manufacture' => 'full_package',
        ];

        foreach ($map as $from => $to) {
            if ($from === $to) {
                continue;
            }
            DB::table('maker_jobs')->where('type', $from)->update(['type' => $to]);
        }

        $types = DB::table('maker_job_types')->pluck('id', 'slug');
        foreach ($types as $slug => $id) {
            DB::table('maker_jobs')->where('type', $slug)->whereNull('type_id')->update(['type_id' => $id]);
        }

        if (Schema::hasColumn('maker_jobs', 'budget') && Schema::hasColumn('maker_jobs', 'budget_max')) {
            DB::table('maker_jobs')->whereNull('budget_max')->whereNotNull('budget')->update([
                'budget_max' => DB::raw('budget'),
            ]);
        }

        DB::table('maker_jobs')->where('status', 'open')->update(['status' => 'quote_request']);
    }
};
