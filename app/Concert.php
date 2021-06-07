<?php

namespace App;

use App\Exceptions\NotEnoughTicketsException;
use Carbon\Carbon;
use App\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Concert extends Model
{
    protected $guarded = [];
//    protected $dates = ['date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    public function isPublished()
    {
        return $this->published_at !== null;
    }

    public function publish()
    {
        $this->update(['published_at' => $this->freshTimestamp()]);
    }

    public function getFormattedDate(): string
    {
        return Carbon::parse($this->date)->format('F j, Y');
    }

    public function getFormattedStartTime(): string
    {
        return Carbon::parse($this->date)->format('g:ia');
    }

    public function getTicketPriceInDollars(): string
    {
        return number_format($this->ticket_price / 100, 2);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'tickets');
    }

    public function hasOrderFor($customerEmail): bool
    {
        return $this->orders()->where('email', $customerEmail)->count() > 0;
    }

    public function ordersFor($customerEmail): Collection
    {
        return $this->orders()->where('email', $customerEmail)->get();
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function reserveTickets($quantity, $email): Reservation
    {
        $tickets = $this->findTickets($quantity)->each(function ($ticket) {
            $ticket->reserve();
        });

        return new Reservation($tickets, $email);
    }

    public function findTickets($quantity)
    {
        $tickets = $this->tickets()->available()->take($quantity)->get();

        if ($tickets->count() < $quantity) {
            throw new NotEnoughTicketsException;
        }

        return $tickets;
    }

    public function addTickets(int $quantity): Concert
    {
        foreach (range(1, $quantity) as $i) {
            $this->tickets()->create([]);
        }

        return $this;
    }

    public function ticketsRemaining()
    {
        return $this->tickets()->available()->count();
    }

    public function ticketsQuantity()
    {
        return $this->tickets()->count();
    }
}
