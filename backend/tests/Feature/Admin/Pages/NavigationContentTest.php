<?php

use App\Filament\Pages\NavigationContent;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

/**
 * The header/footer fields are 2-field Repeaters; Livewire state is a
 * key→['label'=>…, 'href'=>…] map (item ids — any stable strings work in a
 * test). getState() dehydrates back to an ordered list.
 *
 * @param  list<array{label: string, href: string}>  $items
 * @return array<string, array{label: string, href: string}>
 */
function navState(array $items): array
{
    $state = [];
    foreach (array_values($items) as $i => $item) {
        $state["item-{$i}"] = ['label' => $item['label'], 'href' => $item['href']];
    }

    return $state;
}

test('the navigation api returns null lists until an admin saves', function (): void {
    getJson('/api/site-content/navigation')
        ->assertOk()
        ->assertJsonPath('data.header', null)
        ->assertJsonPath('data.footer', null);
});

test('admin and manager can open the navigation page; ops cannot', function (): void {
    $this->actingAsAdmin();
    expect(NavigationContent::canAccess())->toBeTrue();

    $this->actingAsManager();
    expect(NavigationContent::canAccess())->toBeTrue();

    $this->actingAsOps();
    expect(NavigationContent::canAccess())->toBeFalse();
});

test('mount prefills the built-in nav, so an unchanged save persists the defaults', function (): void {
    $this->actingAsAdmin();

    Livewire::test(NavigationContent::class)
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/navigation')
        ->assertJsonPath('data.header', NavigationContent::HEADER_DEFAULTS)
        ->assertJsonPath('data.footer', NavigationContent::FOOTER_DEFAULTS);
});

test('saving custom nav items serves them on the api', function (): void {
    $this->actingAsAdmin();

    Livewire::test(NavigationContent::class)
        ->set('data.header', navState([
            ['label' => 'Films', 'href' => '/movies'],
            ['label' => 'Calendar', 'href' => '/whats-on'],
        ]))
        ->set('data.footer', navState([
            ['label' => 'Reach Us', 'href' => '/contact'],
        ]))
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/navigation')
        ->assertJsonPath('data.header', [
            ['label' => 'Films', 'href' => '/movies'],
            ['label' => 'Calendar', 'href' => '/whats-on'],
        ])
        ->assertJsonPath('data.footer', [
            ['label' => 'Reach Us', 'href' => '/contact'],
        ]);
});

test('an https url is accepted', function (): void {
    $this->actingAsAdmin();

    Livewire::test(NavigationContent::class)
        ->set('data.header', navState([['label' => 'Blog', 'href' => 'https://blog.finalcut.test']]))
        ->set('data.footer', navState([['label' => 'Contact', 'href' => '/contact']]))
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/navigation')
        ->assertJsonPath('data.header.0.href', 'https://blog.finalcut.test');
});

test('a javascript: href is rejected at the form layer and never persists', function (): void {
    $this->actingAsAdmin();

    Livewire::test(NavigationContent::class)
        ->set('data.header', navState([['label' => 'Evil', 'href' => 'javascript:alert(1)']]))
        ->call('save')
        ->assertHasErrors();

    getJson('/api/site-content/navigation')
        ->assertJsonPath('data.header', null);
});

test('a fresh mount reloads the saved nav over the defaults and the cache busts', function (): void {
    $this->actingAsAdmin();

    Livewire::test(NavigationContent::class)
        ->set('data.header', navState([['label' => 'Only Item', 'href' => '/movies']]))
        ->set('data.footer', navState([['label' => 'Only Footer', 'href' => '/contact']]))
        ->call('save');

    getJson('/api/site-content/navigation')
        ->assertJsonPath('data.header', [['label' => 'Only Item', 'href' => '/movies']]);

    // A fresh page instance: mount must prefill the SAVED nav, not the
    // defaults — an unchanged save re-persists it.
    Livewire::test(NavigationContent::class)
        ->call('save');

    getJson('/api/site-content/navigation')
        ->assertJsonPath('data.header', [['label' => 'Only Item', 'href' => '/movies']]);
});
