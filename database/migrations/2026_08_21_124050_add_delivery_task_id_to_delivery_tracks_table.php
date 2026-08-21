<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_tracks', function (Blueprint $table): void {
            $table->foreignId('delivery_task_id')
                ->nullable()
                ->after('order_id')
                ->constrained('delivery_tasks')
                ->cascadeOnDelete();
        });

        $this->backfillDeliveryTaskIds();

        // Add the replacement indexes first: MySQL keeps using the unique
        // order_id index for the foreign key until a plain index exists.
        Schema::table('delivery_tracks', function (Blueprint $table): void {
            $table->index('order_id');
            $table->unique('delivery_task_id');
        });

        Schema::table('delivery_tracks', function (Blueprint $table): void {
            $table->dropUnique(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_tracks', function (Blueprint $table): void {
            $table->index('delivery_task_id');
            $table->unique('order_id');
        });

        Schema::table('delivery_tracks', function (Blueprint $table): void {
            $table->dropUnique(['delivery_task_id']);
            $table->dropIndex(['order_id']);
            $table->dropConstrainedForeignId('delivery_task_id');
        });
    }

    /**
     * Attach each existing track to the delivery person's most recent task for
     * the same order; drop tracks that can no longer be attributed to a task.
     */
    private function backfillDeliveryTaskIds(): void
    {
        DB::table('delivery_tracks')
            ->orderBy('id')
            ->chunkById(100, function ($tracks): void {
                foreach ($tracks as $track) {
                    $taskId = DB::table('delivery_tasks')
                        ->where('order_id', $track->order_id)
                        ->where('user_id', $track->delivery_person_id)
                        ->orderByDesc('id')
                        ->value('id');

                    if ($taskId === null) {
                        DB::table('delivery_tracks')->where('id', $track->id)->delete();

                        continue;
                    }

                    DB::table('delivery_tracks')->where('id', $track->id)->update([
                        'delivery_task_id' => $taskId,
                    ]);
                }
            });

        $duplicatedTaskIds = DB::table('delivery_tracks')
            ->select('delivery_task_id')
            ->groupBy('delivery_task_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('delivery_task_id');

        foreach ($duplicatedTaskIds as $taskId) {
            $keepId = DB::table('delivery_tracks')
                ->where('delivery_task_id', $taskId)
                ->orderBy('id')
                ->value('id');

            DB::table('delivery_tracks')
                ->where('delivery_task_id', $taskId)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }
};
