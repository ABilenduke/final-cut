<?php

use App\Filament\Resources\MenuItemResource;
use App\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Models\MenuItem;

test('ops can view the menu list but cannot create, edit, or delete', function (): void {
    $this->actingAsOps();
    $item = MenuItem::factory()->create();

    expect(MenuItemResource::canViewAny())->toBeTrue();
    expect(MenuItemResource::canCreate())->toBeFalse();
    expect(MenuItemResource::canEdit($item))->toBeFalse();
    expect(MenuItemResource::canDelete($item))->toBeFalse();

    $this->get(CreateMenuItem::getUrl())->assertForbidden();
});

test('manager can create, edit, and delete menu items', function (): void {
    $this->actingAsManager();
    $item = MenuItem::factory()->create();

    expect(MenuItemResource::canViewAny())->toBeTrue();
    expect(MenuItemResource::canCreate())->toBeTrue();
    expect(MenuItemResource::canEdit($item))->toBeTrue();
    expect(MenuItemResource::canDelete($item))->toBeTrue();
});

test('nobody role cannot see menu list', function (): void {
    $this->actingAsNobody();

    expect(MenuItemResource::canViewAny())->toBeFalse();

    $this->get(ListMenuItems::getUrl())->assertForbidden();
});
