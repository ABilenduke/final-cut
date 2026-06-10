<?php

use App\Filament\Resources\MovieResource\Pages\EditMovie;
use App\Models\Movie;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

test('the movie detail api exposes editorial credits, press quotes, and clips', function (): void {
    $movie = Movie::factory()->create([
        'credits' => ['director' => 'Denis Villeneuve', 'composer' => 'Hans Zimmer'],
        'press_quotes' => [
            ['quote' => 'A trilogy closer of monastic restraint.', 'author' => 'Léa Ferrand', 'publication' => 'Sight & Sound'],
        ],
        'clips' => [
            ['label' => 'Teaser — First Look', 'sub' => 'Released 2026', 'duration' => '1:14', 'youtube_key' => 'abc123def45'],
        ],
    ]);

    getJson("/api/movies/{$movie->slug}")
        ->assertOk()
        ->assertJsonPath('data.credits.director', 'Denis Villeneuve')
        ->assertJsonPath('data.pressQuotes.0.publication', 'Sight & Sound')
        ->assertJsonPath('data.clips.0.youtube_key', 'abc123def45');
});

test('movies without editorial data expose nulls, not errors', function (): void {
    $movie = Movie::factory()->create(['credits' => null, 'press_quotes' => null, 'clips' => null]);

    getJson("/api/movies/{$movie->slug}")
        ->assertOk()
        ->assertJsonPath('data.credits', null)
        ->assertJsonPath('data.pressQuotes', [])
        ->assertJsonPath('data.clips', []);
});

test('an admin can edit the editorial fields through the movie form', function (): void {
    $this->actingAsAdmin();
    $movie = Movie::factory()->create();

    Livewire::test(EditMovie::class, ['record' => $movie->getKey()])
        ->set('data.credits.director', 'Greta Gerwig')
        ->set('data.press_quotes', [
            ['quote' => 'Luminous.', 'author' => 'A. Critic', 'publication' => 'The Times'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $movie->refresh();
    expect($movie->credits['director'])->toBe('Greta Gerwig')
        ->and($movie->press_quotes[0]['publication'])->toBe('The Times');
});
