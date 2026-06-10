<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRolesAndPermissionsSeeder extends Seeder
{
    /**
     * The complete set of admin permissions, grouped by resource.
     *
     * Customer users (the `users` table on the customer side) are read-only
     * from admin in v1 — no `users.update` permission is seeded. Loyalty
     * writes are exposed through the two narrower `loyalty.*` permissions.
     */
    public const PERMISSIONS = [
        // Movies
        'movies.view', 'movies.create', 'movies.update', 'movies.delete', 'movies.trigger_enrich',
        // Showtimes
        'showtimes.view', 'showtimes.create', 'showtimes.update', 'showtimes.delete', 'showtimes.cancel',
        // Locations
        'locations.view', 'locations.create', 'locations.update', 'locations.delete',
        // Auditoriums
        'auditoriums.view', 'auditoriums.create', 'auditoriums.update', 'auditoriums.delete',
        // Seats
        'seats.view', 'seats.update',
        // Bookings
        'bookings.view', 'bookings.resolve_refund',
        'bookings.flag', 'bookings.resend_confirmation',
        'bookings.create_walkup',
        // Customer users (read-only in v1)
        'users.view',
        // Loyalty (narrow, no broad `loyalty.adjust`)
        'loyalty.view', 'loyalty.adjust_points', 'loyalty.adjust_tier',
        // Menu
        'menu.view', 'menu.create', 'menu.update', 'menu.delete',
        // Promo codes
        'promos.view', 'promos.create', 'promos.update', 'promos.delete',
        // Gift cards
        'gift_cards.view', 'gift_cards.void',
        // Calendar events
        'events.view', 'events.create', 'events.update', 'events.delete',
        // Admin users
        'admin_users.view', 'admin_users.create', 'admin_users.update', 'admin_users.delete',
        // Activity log
        'activity.view',
        // Dispatch outbox (admin-only ops surface — not granted to manager/ops)
        'outbox.view', 'outbox.retry',
        // Inquiry inboxes
        'rentals.view', 'rentals.update_status',
        'contact.view', 'contact.resolve',
        // Marketing — Featured Slides
        'marketing.featured_slides.view', 'marketing.featured_slides.create',
        'marketing.featured_slides.update', 'marketing.featured_slides.delete',
        'marketing.ticker.view', 'marketing.ticker.create',
        'marketing.ticker.update', 'marketing.ticker.delete',
        // Content — Blog
        'content.blog.view', 'content.blog.create',
        'content.blog.update', 'content.blog.delete',
        'content.faq.view', 'content.faq.create',
        'content.faq.update', 'content.faq.delete',
        'content.careers.view', 'content.careers.create',
        'content.careers.update', 'content.careers.delete',
        'content.packages.view', 'content.packages.create',
        'content.packages.update', 'content.packages.delete',
        // Content — Site settings (home page editorial blobs)
        'content.site_settings.update',
    ];

    public const MANAGER_PERMISSIONS = [
        'movies.view', 'movies.create', 'movies.update', 'movies.delete', 'movies.trigger_enrich',
        'showtimes.view', 'showtimes.create', 'showtimes.update', 'showtimes.delete', 'showtimes.cancel',
        'locations.view', 'locations.update',
        'auditoriums.view', 'auditoriums.create', 'auditoriums.update', 'auditoriums.delete',
        'seats.view', 'seats.update',
        'menu.view', 'menu.create', 'menu.update', 'menu.delete',
        'events.view', 'events.create', 'events.update', 'events.delete',
        'promos.view', 'promos.create', 'promos.update', 'promos.delete',
        'bookings.view', 'bookings.resolve_refund',
        'bookings.flag', 'bookings.resend_confirmation',
        'bookings.create_walkup',
        'rentals.view', 'rentals.update_status',
        'contact.view', 'contact.resolve',
        'gift_cards.view', 'gift_cards.void',
        'users.view',
        'loyalty.view', 'loyalty.adjust_points', 'loyalty.adjust_tier',
        'activity.view',
        'marketing.featured_slides.view', 'marketing.featured_slides.create',
        'marketing.featured_slides.update', 'marketing.featured_slides.delete',
        'marketing.ticker.view', 'marketing.ticker.create',
        'marketing.ticker.update', 'marketing.ticker.delete',
        // Content — Blog
        'content.blog.view', 'content.blog.create',
        'content.blog.update', 'content.blog.delete',
        'content.faq.view', 'content.faq.create',
        'content.faq.update', 'content.faq.delete',
        'content.careers.view', 'content.careers.create',
        'content.careers.update', 'content.careers.delete',
        'content.packages.view', 'content.packages.create',
        'content.packages.update', 'content.packages.delete',
        // Content — Site settings (home page editorial blobs)
        'content.site_settings.update',
    ];

    public const OPS_PERMISSIONS = [
        'bookings.view', 'users.view', 'loyalty.view', 'gift_cards.view',
        'rentals.view', 'contact.view',
        'movies.view', 'showtimes.view', 'locations.view', 'auditoriums.view',
        'menu.view', 'promos.view',
        'activity.view',
    ];

    public function run(): void
    {
        // Clear Spatie's permission cache so freshly-created permissions
        // are visible to syncPermissions() in this same run.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        $admin->syncPermissions(Permission::where('guard_name', 'admin')->get());

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'admin']);
        $manager->syncPermissions(self::MANAGER_PERMISSIONS);

        $ops = Role::firstOrCreate(['name' => 'ops', 'guard_name' => 'admin']);
        $ops->syncPermissions(self::OPS_PERMISSIONS);
    }
}
