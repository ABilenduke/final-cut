<?php

use App\Filament\Resources\BaseResource;
use App\Models\User;

// Fixture classes declared inline at file-top. Filament's Resource::getPages()
// is abstract in practice; providing a no-op keeps the fixtures instantiable.
class MoviesResourceStub extends BaseResource
{
    protected static ?string $permissionPrefix = 'movies';

    public static function getPages(): array
    {
        return [];
    }

    // Exposed only so a test can exercise the protected crudPermission()
    // verb guard without having to reach through reflection.
    public static function exposeCrudPermission(string $action): string
    {
        return static::crudPermission($action);
    }
}

class MissingPrefixResourceStub extends BaseResource
{
    public static function getPages(): array
    {
        return [];
    }
}

test('canViewAny returns true for a user with the view permission', function (): void {
    $this->actingAsManager();

    expect(MoviesResourceStub::canViewAny())->toBeTrue();
});

test('canCreate returns true for admin (has all permissions)', function (): void {
    $this->actingAsAdmin();

    expect(MoviesResourceStub::canCreate())->toBeTrue();
});

test('canCreate returns false for ops (no movies.create)', function (): void {
    $this->actingAsOps();

    expect(MoviesResourceStub::canCreate())->toBeFalse();
});

test('canEdit returns true for admin', function (): void {
    $this->actingAsAdmin();
    $record = new User;

    expect(MoviesResourceStub::canEdit($record))->toBeTrue();
});

test('canEdit returns false for ops', function (): void {
    $this->actingAsOps();
    $record = new User;

    expect(MoviesResourceStub::canEdit($record))->toBeFalse();
});

test('canDelete returns true for admin and false for ops', function (): void {
    $admin = $this->actingAsAdmin();
    $record = new User;
    expect(MoviesResourceStub::canDelete($record))->toBeTrue();

    $this->actingAsOps();
    expect(MoviesResourceStub::canDelete($record))->toBeFalse();
});

test('all CRUD methods return false for an admin user with no role', function (): void {
    $this->actingAsNobody();
    $record = new User;

    expect(MoviesResourceStub::canViewAny())->toBeFalse();
    expect(MoviesResourceStub::canCreate())->toBeFalse();
    expect(MoviesResourceStub::canEdit($record))->toBeFalse();
    expect(MoviesResourceStub::canDelete($record))->toBeFalse();
});

test('missing $permissionPrefix throws LogicException naming the Resource class', function (): void {
    $this->actingAsAdmin();

    expect(fn () => MissingPrefixResourceStub::canViewAny())
        ->toThrow(LogicException::class, 'MissingPrefixResourceStub');
});

test('crudPermission rejects non-CRUD verbs', function (): void {
    expect(fn () => MoviesResourceStub::exposeCrudPermission('trigger_enrich'))
        ->toThrow(LogicException::class, 'CRUD');
});
