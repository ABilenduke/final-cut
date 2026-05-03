<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_slides', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Display content
            $table->string('headline', 80);            // 1-80 chars; validated at write time (Filament / API)
            $table->string('sub_headline', 160)->nullable(); // ≤ 160 chars; optional

            // Media — public URL; file uploaded via Filament FileUpload against disk('public').
            // Nullable so drafts can be saved before an image is attached.
            $table->text('image_url')->nullable();

            // Call-to-action
            $table->string('cta_label', 24);          // ≤ 24 chars (fits button width)
            $table->text('cta_href');                  // URL or internal route path (e.g. /events/final-cut-selects)

            // Ordering — lower number surfaces first; ties broken by id (UUIDv4 = random)
            $table->unsignedInteger('display_order')->default(0);

            // Publish window — null bounds mean "no constraint on that side"
            // null published_at = draft (never shown publicly)
            // starts_at null = no lower bound (show from publish date onward)
            // ends_at null   = no upper bound (show indefinitely once published)
            //
            // Graduation criteria for starts_at/ends_at → dedicated scheduling table:
            // if per-slide scheduling logic grows to include recurrence, timezone
            // overrides, or more than 2 date dimensions per slide.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // Composite index optimised for the active-window scan used by scopeActive:
            //   WHERE published_at IS NOT NULL
            //     AND published_at <= now()
            //     AND (starts_at IS NULL OR starts_at <= now())
            //     AND (ends_at IS NULL OR ends_at >= now())
            //   ORDER BY display_order
            $table->index(
                ['published_at', 'starts_at', 'ends_at', 'display_order'],
                'featured_slides_active_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_slides');
    }
};
