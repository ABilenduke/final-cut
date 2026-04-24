<?php

use App\Filament\Resources\LocationResource;
use App\Filament\Resources\LocationResource\Pages\CreateLocation;
use App\Filament\Resources\LocationResource\Pages\ListLocations;
use App\Models\Location;

test('ops can view the location list but cannot create, edit, or delete', function (): void {
    $this->actingAsOps();
    $location = Location::factory()->create();

    expect(LocationResource::canViewAny())->toBeTrue();
    expect(LocationResource::canCreate())->toBeFalse();
    expect(LocationResource::canEdit($location))->toBeFalse();
    expect(LocationResource::canDelete($location))->toBeFalse();

    $this->get(CreateLocation::getUrl())->assertForbidden();
});

test('managers can view and edit locations but not create or delete them', function (): void {
    $this->actingAsManager();
    $location = Location::factory()->create();

    // Matches AdminRolesAndPermissionsSeeder::MANAGER_PERMISSIONS — manager
    // has locations.view + locations.update, no create/delete.
    expect(LocationResource::canViewAny())->toBeTrue();
    expect(LocationResource::canCreate())->toBeFalse();
    expect(LocationResource::canEdit($location))->toBeTrue();
    expect(LocationResource::canDelete($location))->toBeFalse();
});

test('admins have full CRUD on locations', function (): void {
    $this->actingAsAdmin();
    $location = Location::factory()->create();

    expect(LocationResource::canViewAny())->toBeTrue();
    expect(LocationResource::canCreate())->toBeTrue();
    expect(LocationResource::canEdit($location))->toBeTrue();
    expect(LocationResource::canDelete($location))->toBeTrue();
});

test('an admin user with no role cannot view the location list', function (): void {
    $this->actingAsNobody();

    expect(LocationResource::canViewAny())->toBeFalse();

    $this->get(ListLocations::getUrl())->assertForbidden();
});
