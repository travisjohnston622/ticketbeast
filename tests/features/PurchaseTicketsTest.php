<?php

use App\Concert;
use App\Billing\FakePaymentGateway;
use App\Billing\PaymentGateway;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PurchaseTicketsTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    function customer_can_purchase_tickets_to_a_published_concert()
    {
        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);

        $concert = factory(Concert::class)->states('published')->create(['ticket_price' => 3250]);

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'email' => 'travis@example.com',
            'ticket_quantity' => 2,
            'payment_token' => $paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(200);

        $this->assertEquals(6500, $paymentGateway->totalCharges());

        $order = $concert->orders()->where('email', 'travis@example.com')->first();
        $this->assertNotNull($order);
        $this->assertEquals(2, $order->tickets->count());
    }

    /** @test */
    function cannot_purchase_tickets_to_an_unpublished_concert()
    {
        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);

        $concert = factory(Concert::class)->states('unpublished')->create();

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'email' => 'travis@example.com',
            'ticket_quantity' => 2,
            'payment_token' => $paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(404);
        $this->assertEquals(0, $concert->orders()->count());
        $this->assertEquals(0, $paymentGateway->totalCharges());
    }

    /** @test */
    function an_order_is_not_created_if_payment_fails()
    {
        $this->disableExceptionHandling();
        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);

        $concert = factory(Concert::class)->states('published')->create(['ticket_price' => 3250]);

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'email' => 'travis@example.com',
            'ticket_quantity' => 2,
            'payment_token' => 'invalid-payment-token',
        ]);

        $response->assertStatus(422);
        $order = $concert->orders()->where('email', 'travis@example.com')->first();
        $this->assertNull($order);
    }

    /** @test */
    function cannot_purchase_more_tickets_than_remain()
    {
        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);

        $concert = factory(Concert::class)->states('published')->create();
        $concert -> addTickets(50);

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'email' => 'travis@example.com',
            'ticket_quantity' => 52,
            'payment_token' => $paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(422);
        $order = $concert->orders()->where('email', 'travis@example.com')->first();
        $this->assertNull($order);
        $this->assertEquals(0, $paymentGateway->totalCharges());
        $this->assertEquals(50, $concert->ticketRemaining());


    }

    /** @test */
    function email_is_required_to_purchase_tickets()
    {

        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);
        $concert = factory(Concert::class)->states('published')->create();

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'ticket_quantity' => 2,
            'payment_token' => $paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(422);
    }
}
