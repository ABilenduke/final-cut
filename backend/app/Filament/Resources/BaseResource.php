<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

abstract class BaseResource extends Resource
{
    /** Permission prefix for CRUD checks, e.g., 'movies', 'showtimes'. */
    protected static ?string $permissionPrefix = null;

    public static function canViewAny(): bool
    {
        return auth('admin')->user()?->can(static::crudPermission('view')) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth('admin')->user()?->can(static::crudPermission('create')) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth('admin')->user()?->can(static::crudPermission('update')) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth('admin')->user()?->can(static::crudPermission('delete')) ?? false;
    }

    /**
     * CRUD-only permission resolver. Custom actions must bind their own permission
     * at the call site — do not extend this to arbitrary verbs.
     */
    protected static function crudPermission(string $action): string
    {
        if (! in_array($action, ['view', 'create', 'update', 'delete'], true)) {
            throw new \LogicException(
                'BaseResource::crudPermission only handles CRUD verbs. '
                ."Custom action '{$action}' must declare its own permission at the call site."
            );
        }

        $prefix = static::$permissionPrefix
            ?? throw new \LogicException(static::class.' must declare $permissionPrefix');

        return "{$prefix}.{$action}";
    }
}
