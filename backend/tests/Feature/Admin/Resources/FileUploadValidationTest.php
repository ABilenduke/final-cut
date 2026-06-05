<?php

use App\Filament\Resources\CalendarEventResource\Pages\CreateCalendarEvent;
use App\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAsAdmin();
    Storage::fake('public');
});

/*
|--------------------------------------------------------------------------
| Public-disk image uploads must reject SVG (XML → stored XSS off the
| public disk) and accept only raster types. ->image() alone permits the
| whole image/* family, including image/svg+xml.
|--------------------------------------------------------------------------
*/

test('CalendarEvent image_path rejects an SVG upload', function (): void {
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.image_path', UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'))
        ->call('create')
        ->assertHasFormErrors(['image_path']);
});

test('CalendarEvent image_path accepts a raster (jpeg) upload', function (): void {
    Livewire::test(CreateCalendarEvent::class)
        ->set('data.image_path', UploadedFile::fake()->image('poster.jpg'))
        ->call('create')
        ->assertHasNoFormErrors(['image_path']);
});

test('MenuItem image_url rejects an SVG upload', function (): void {
    Livewire::test(CreateMenuItem::class)
        ->set('data.image_url', UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'))
        ->call('create')
        ->assertHasFormErrors(['image_url']);
});

test('MenuItem image_url accepts a raster (jpeg) upload', function (): void {
    Livewire::test(CreateMenuItem::class)
        ->set('data.image_url', UploadedFile::fake()->image('dish.jpg'))
        ->call('create')
        ->assertHasNoFormErrors(['image_url']);
});
