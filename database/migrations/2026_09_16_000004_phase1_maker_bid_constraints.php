<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maker_bids')) {
            if (! Schema::hasColumn('maker_bids', 'company_id')) {
                Schema::table('maker_bids', function (Blueprint $table) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('user_id');
                });
            }

            $this->dedupeBidsByJobUser();
            $this->replaceJobUserIndexWithUnique();
        }

        if (Schema::hasTable('maker_companies')) {
            if (! Schema::hasColumn('maker_companies', 'rejected_reason')) {
                Schema::table('maker_companies', function (Blueprint $table) {
                    $table->string('rejected_reason', 2000)->nullable()->after('note');
                });
            }
            if (! Schema::hasColumn('maker_companies', 'reviewed_at')) {
                Schema::table('maker_companies', function (Blueprint $table) {
                    $table->timestamp('reviewed_at')->nullable()->after('rejected_reason');
                });
            }

            $this->dedupeCompaniesByUser();
            $this->ensureCompanyUserUnique();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('maker_bids')) {
            $this->safeDropIndex('maker_bids', 'maker_bids_job_user_unique');
            if (! $this->hasIndexNamed('maker_bids', 'maker_bids_job_id_user_id_index')) {
                Schema::table('maker_bids', function (Blueprint $table) {
                    $table->index(['job_id', 'user_id']);
                });
            }
            if (Schema::hasColumn('maker_bids', 'company_id')) {
                Schema::table('maker_bids', function (Blueprint $table) {
                    $table->dropColumn('company_id');
                });
            }
        }

        if (Schema::hasTable('maker_companies')) {
            $this->safeDropIndex('maker_companies', 'maker_companies_user_id_unique');
            foreach (['reviewed_at', 'rejected_reason'] as $column) {
                if (Schema::hasColumn('maker_companies', $column)) {
                    Schema::table('maker_companies', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }

    private function dedupeBidsByJobUser(): void
    {
        $dupes = DB::table('maker_bids')
            ->select('job_id', 'user_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('job_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $row) {
            DB::table('maker_bids')
                ->where('job_id', $row->job_id)
                ->where('user_id', $row->user_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }
    }

    private function replaceJobUserIndexWithUnique(): void
    {
        if ($this->hasIndexNamed('maker_bids', 'maker_bids_job_user_unique')) {
            return;
        }

        $this->safeDropIndex('maker_bids', 'maker_bids_job_id_user_id_index');

        try {
            Schema::table('maker_bids', function (Blueprint $table) {
                $table->unique(['job_id', 'user_id'], 'maker_bids_job_user_unique');
            });
        } catch (\Throwable) {
        }
    }

    private function dedupeCompaniesByUser(): void
    {
        $dupes = DB::table('maker_companies')
            ->select('user_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $row) {
            DB::table('maker_companies')
                ->where('user_id', $row->user_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }
    }

    private function ensureCompanyUserUnique(): void
    {
        if ($this->hasIndexNamed('maker_companies', 'maker_companies_user_id_unique')) {
            return;
        }

        try {
            Schema::table('maker_companies', function (Blueprint $table) {
                $table->unique('user_id');
            });
        } catch (\Throwable) {
        }
    }

    private function hasIndexNamed(string $table, string $index): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $row) {
                $name = $row['name'] ?? $row['index_name'] ?? null;
                if ($name === $index) {
                    return true;
                }
            }
        } catch (\Throwable) {
        }

        return false;
    }

    private function safeDropIndex(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropIndex($index);
            });
        } catch (\Throwable) {
        }
    }
};
