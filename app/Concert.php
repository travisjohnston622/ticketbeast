<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Concert extends Model
{
    protected $guarded = [];
    protected $date = ['date'];

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    public function getFormattedDate()
    {
        return $this->date->format('F j, Y');
    }

    public function getFormattedStartTime()
    {
        return $this->date->format('g:ia');
    }
    public function getTicketPriceInDollarsAttribute()
    {
        return number_format($this->ticket_price / 100, 2);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function orderTickets($email, $ticketQuantity)
    {
        $order = $this->orders()->create(['email' => $email]);

        foreach (range(1, $ticketQuantity) as $i) {
            $order->tickets()->create([]);
        }

        return $order;
    }
}
