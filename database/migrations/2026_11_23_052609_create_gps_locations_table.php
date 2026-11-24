<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gps_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy');                 // metres
            $table->timestamp('recorded_at');          
            $table->boolean('is_valid')->default(true);
            $table->string('reject_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'recorded_at']);        // blocks duplicates
            $table->index(['user_id', 'recorded_at']);         // main report query
            
            $table->index('recorded_at');                      // all-users reports
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_locations');
    }
};
