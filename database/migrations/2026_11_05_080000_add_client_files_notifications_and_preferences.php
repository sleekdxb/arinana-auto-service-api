<?php

use App\Models\BookingState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->dropUniqueOn('booking_files', 'book_id');
        $this->dropUniqueOn('vehicle_files', 'veh_id');

        if (!Schema::hasColumn('booking_file_states', 'note')) {
            Schema::table('booking_file_states', function (Blueprint $table) {
                $table->text('note')->nullable();
            });
        }

        if (!Schema::hasColumn('clients', 'profile_img')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('profile_img')->nullable();
            });
        }

        if (!Schema::hasColumn('client_sessions', 'fcm_token')) {
            Schema::table('client_sessions', function (Blueprint $table) {
                $table->text('fcm_token')->nullable();
            });
        }

        if (!Schema::hasTable('client_notifications')) {
            Schema::create('client_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('notif_id')->unique();
                $table->string('client_id')->index();
                $table->string('type')->index();
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('ref_id')->nullable()->index();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('client_preferences')) {
            Schema::create('client_preferences', function (Blueprint $table) {
                $table->id();
                $table->string('client_id')->unique();
                $table->boolean('push_enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->boolean('sms_enabled')->default(false);
                $table->boolean('booking_updates')->default(true);
                $table->boolean('service_status_updates')->default(true);
                $table->boolean('service_completion')->default(true);
                $table->boolean('document_review')->default(true);
                $table->timestamps();
            });
        }

        foreach (['booking_statuses', 'vehicle_statuses'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->select('id', 'name')->orderBy('id')->each(function ($row) use ($table) {
                $name = BookingState::normalizeName($row->name);

                if ($name !== $row->name) {
                    DB::table($table)->where('id', $row->id)->update(['name' => $name]);
                }
            });
        }

        DB::table('bookings')->select('id', 'vehicle_ids')->orderBy('id')->each(function ($row) {
            $decoded = json_decode((string) $row->vehicle_ids, true);

            if (is_string($decoded)) {
                DB::table('bookings')->where('id', $row->id)->update(['vehicle_ids' => $decoded]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_preferences');
        Schema::dropIfExists('client_notifications');
    }

    private function dropUniqueOn(string $table, string $column): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && !$index['primary'] && $index['columns'] === [$column]) {
                Schema::table($table, function (Blueprint $blueprint) use ($index, $column) {
                    $blueprint->dropUnique($index['name']);
                    $blueprint->index($column);
                });
            }
        }
    }
};
