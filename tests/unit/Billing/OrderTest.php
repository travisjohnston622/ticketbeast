<?php

use App\Concert;
use App\Order;
use App\Reservation;
use App\Ticket;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class OrderTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    function creating_an_order_from_tickets_email_an_amount()
    {
        $concert = factory(Concert::class)->create()->addTickets(5);
        $this->assertEquals(5, $concert->ticketsRemaining());

        $order = Order::forTickets($concert->findTickets(3), 'travis@example.com', 3600);

        $this->assertEquals('travis@example.com', $order->email);
        $this->assertEquals(3, $order->ticketQuantity());
        $this->assertEquals(3600, $order->amount);
        $this->assertEquals(2, $concert->ticketsRemaining());

    }

    /** @test */
    function converting_to_an_array()
    {
        $concert = factory(Concert::class)->create(['ticket_price' => 1200])->addTickets(5);
        $order = Order::forTickets($concert->findTickets(5), 'travis@example.com', 6000);

        $result = $order->toArray();

        $this->assertEquals([
            'email' => 'travis@example.com',
            'ticket_quantity' => 5,
            'amount' => 6000,
        ], $result);
    }
}
