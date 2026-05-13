<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'confirmationCode' => $this->confirmation_code,
            'showtimeId' => $this->showtime_id,
            'movieTitle' => $this->showtime->movie->title,
            'moviePosterUrl' => $this->showtime->movie->poster_url,
            'screenName' => $this->showtime->auditorium->name,
            'startTime' => $this->showtime->start_time->toIso8601String(),
            'seats' => $this->seats->map(fn (mixed $s) => [
                'id' => $s->seat_id,
                'label' => $s->seat->label,
                'section' => $s->section,
                'price' => $s->price,
            ])->values(),
            'foodItems' => $this->foodItems->map(fn (mixed $f) => [
                'itemId' => $f->menu_item_id,
                'name' => $f->name,
                'quantity' => $f->quantity,
                'unitPrice' => $f->unit_price,
                'totalPrice' => $f->total_price,
            ])->values(),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'paymentMethod' => $this->payment_method?->value,
            'userId' => $this->user_id,
            'guestEmail' => $this->guest_email,
            'status' => $this->status->value,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
