<?php

use Filament\Resources\Resource;

/**
 * Filament orders navigation items within a group by `navigationSort`. When two
 * resources in the same group share a sort value, their relative order is
 * non-deterministic (it falls back to discovery order, which can shift between
 * deploys). This guard pins that every resource has a distinct sort within its
 * group — the Operations group previously had Booking/Location both at 20 and
 * User/Auditorium/GiftCard all at 30.
 *
 * Scoped to Resources (Pages share the same per-group sort space but were not
 * part of the collision and use a separate, lower band).
 */
function adminResourceClasses(): array
{
    $dir = app_path('Filament/Resources');
    $classes = [];

    foreach (glob($dir.'/*Resource.php') as $file) {
        $class = 'App\\Filament\\Resources\\'.basename($file, '.php');
        if (class_exists($class) && is_subclass_of($class, Resource::class)) {
            $classes[] = $class;
        }
    }

    return $classes;
}

function staticPropOrNull(string $class, string $property): mixed
{
    $ref = new ReflectionClass($class);
    if (! $ref->hasProperty($property)) {
        return null;
    }
    $prop = $ref->getProperty($property);
    $prop->setAccessible(true);

    return $prop->getValue();
}

it('discovers the admin resource classes', function () {
    expect(adminResourceClasses())->not->toBeEmpty();
});

it('assigns a distinct navigationSort to every resource within each navigation group', function () {
    $byGroup = [];

    foreach (adminResourceClasses() as $class) {
        $group = staticPropOrNull($class, 'navigationGroup');
        $groupKey = $group instanceof UnitEnum ? $group->name : (string) ($group ?? '(none)');
        $byGroup[$groupKey][class_basename($class)] = staticPropOrNull($class, 'navigationSort');
    }

    foreach ($byGroup as $groupKey => $sortsByResource) {
        $counts = array_count_values(array_map('strval', $sortsByResource));
        $collisions = array_keys(array_filter($counts, fn ($n) => $n > 1));

        expect($collisions)->toBe(
            [],
            "Navigation group '{$groupKey}' has resources sharing navigationSort ["
            .implode(', ', $collisions).'] — assign distinct values. Map: '
            .json_encode($sortsByResource)
        );
    }
});
