<?php

namespace Database\Seeders;

use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use App\Support\SeederUuid;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CalendarEventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'type' => CalendarEventType::SpecialEvent,
                'title' => 'Classic Movie Marathon: Hitchcock Night',
                'description' => 'Three Hitchcock masterpieces back-to-back. Includes intermission snacks.',
                'days_offset' => 3,
                'time' => '18:00',
                'duration' => 360,
                'accessibility_tags' => [],
            ],
            [
                'type' => CalendarEventType::SpecialEvent,
                'title' => 'Sensory Friendly Saturday',
                'description' => 'Adjusted lighting and sound levels. Doors remain open. A welcoming environment for all.',
                'days_offset' => 5,
                'time' => '10:00',
                'duration' => 150,
                'accessibility_tags' => ['sensory_friendly'],
            ],
            [
                'type' => CalendarEventType::LoyaltyExclusive,
                'title' => 'Premier Members Preview: Starfall',
                'description' => 'Exclusive early screening for Premier members. Complimentary popcorn.',
                'days_offset' => 7,
                'time' => '19:00',
                'duration' => 180,
                'accessibility_tags' => [],
            ],
            [
                'type' => CalendarEventType::SpecialEvent,
                'title' => 'Open Caption Screening: The Verdict',
                'description' => 'Open captions displayed for the hearing impaired.',
                'days_offset' => 8,
                'time' => '14:00',
                'duration' => 150,
                'accessibility_tags' => ['open_caption'],
            ],
            [
                'type' => CalendarEventType::SpecialEvent,
                'title' => 'Kids\' Film Festival',
                'description' => 'A weekend of family-friendly animated features with activities in the lobby.',
                'days_offset' => 10,
                'time' => '09:00',
                'duration' => 480,
                'accessibility_tags' => ['sensory_friendly'],
            ],
            [
                'type' => CalendarEventType::PrivateScreeningBlackout,
                'title' => 'IMAX Private Event',
                'description' => 'IMAX theater unavailable for regular showtimes.',
                'days_offset' => 12,
                'time' => '18:00',
                'duration' => 240,
                'accessibility_tags' => [],
            ],
            [
                'type' => CalendarEventType::SpecialEvent,
                'title' => 'Audio Described Screening: Midnight in Venice',
                'description' => 'Full audio description track available via headset.',
                'days_offset' => 6,
                'time' => '15:00',
                'duration' => 140,
                'accessibility_tags' => ['audio_described'],
            ],
            [
                'type' => CalendarEventType::LoyaltyExclusive,
                'title' => 'Member Appreciation Night',
                'description' => 'Free screening and refreshments for all loyalty members.',
                'days_offset' => 14,
                'time' => '19:30',
                'duration' => 150,
                'accessibility_tags' => [],
            ],
            [
                'type' => CalendarEventType::SpecialEvent,
                'title' => 'Director\'s Q&A: The Cartographer\'s Daughter',
                'description' => 'Post-screening Q&A with the director. Limited seating.',
                'days_offset' => 9,
                'time' => '19:00',
                'duration' => 210,
                'accessibility_tags' => ['open_caption'],
            ],
            [
                'type' => CalendarEventType::SpecialEvent,
                'title' => 'Late Night Horror Double Feature',
                'description' => 'The Deep Below + a surprise second film. Not for the faint of heart.',
                'days_offset' => 11,
                'time' => '22:00',
                'duration' => 300,
                'accessibility_tags' => [],
            ],
        ];

        foreach ($events as $event) {
            $date = Carbon::today()->addDays($event['days_offset']);
            $startTime = $date->copy()->setTimeFromTimeString($event['time']);
            $slug = Str::slug($event['title']);

            CalendarEvent::create([
                'id' => SeederUuid::for("event:{$slug}"),
                'type' => $event['type'],
                'title' => $event['title'],
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $startTime->copy()->addMinutes($event['duration']),
                'description' => $event['description'],
                'slug' => $slug,
                'loyalty_only' => $event['type'] === CalendarEventType::LoyaltyExclusive,
                'accessibility_tags' => $event['accessibility_tags'],
            ]);
        }
    }
}
