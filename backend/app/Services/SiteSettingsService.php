<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;

/**
 * Write boundary for keyed editorial blobs (admin-v2 Plan 15). Every set()
 * runs in a transaction, stamps the actor, and logs activity; the
 * SiteSettingObserver bumps the public cache version on save.
 */
class SiteSettingsService
{
    use LogsAdminActivity;

    public const EVENT_UPDATED = 'site_settings.updated';

    /** The home-page membership pitch blob (frontend MembershipContent shape). */
    public const KEY_HOME_MEMBERSHIP = 'home_membership';

    /** Site-wide contact details (frontend SiteContacts shape). */
    public const KEY_SITE_CONTACTS = 'site_contacts';

    /** Careers-page "why work here" benefits — { benefits: string[] }. */
    public const KEY_CAREERS_BENEFITS = 'careers_benefits';

    /** Contact-page "getting here" prose — { byCar, byTransit, accessibility }. */
    public const KEY_CONTACT_INFO = 'contact_info';

    /** Private-screenings page intro copy — { title, intro }. */
    public const KEY_PRIVATE_SCREENINGS = 'private_screenings';

    /** Accessibility-page prose — intro + the six section paragraphs. */
    public const KEY_ACCESSIBILITY_STATEMENT = 'accessibility_statement';

    /** Header + footer navigation — { header: [{label, href}], footer: [...] }. */
    public const KEY_NAVIGATION = 'navigation';

    public function get(string $key, mixed $default = null): mixed
    {
        return SiteSetting::query()->find($key)->value ?? $default;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function set(string $key, array $value, User $actor): void
    {
        DB::transaction(function () use ($key, $value, $actor): void {
            $setting = SiteSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by' => $actor->id],
            );

            $this->logIfAdmin(self::EVENT_UPDATED, $setting, $actor, [
                'key' => $key,
            ]);
        });
    }
}
