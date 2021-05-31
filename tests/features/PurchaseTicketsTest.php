<?php

use App\Concert;
use App\Billing\FakePaymentGateway;
use App\Billing\PaymentGateway;
use App\Facades\OrderConfirmationNumber;
use App\Facades\TicketCode;
use App\OrderConfirmationNumberGenerator;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PurchaseTicketsTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    function customer_can_purchase_tickets_to_a_published_concert()
    {
        $this->withoutExceptionHandling();
        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);

        OrderConfirmationNumber::shouldReceive('generate')->andReturn('ORDERCONFIRMATION1234');
        TicketCode::shouldReceive('generateFor')->andReturn('TICKETCODE1', 'TICKETCODE2', 'TICKETCODE3');

        $concert = factory(Concert::class)->states('published')->create(['ticket_price' => 3250])->addTickets(3);

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'email' => 'travis@example.com',
            'ticket_quantity' => 3,
            'payment_token' => $paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'confirmation_number' => 'ORDERCONFIRMATION1234',
            'email' => 'travis@example.com',
            'amount' => 9750,
            'tickets' => [
                ['code' => 'TICKETCODE1'],
                ['code' => 'TICKETCODE2'],
                ['code' => 'TICKETCODE3'],
            ]
        ]);

        $this->assertEquals(9750, $paymentGateway->totalCharges());
        $this->assertTrue($concert->hasOrderFor('travis@example.com'));
        $this->assertEquals(3, $concert->ordersFor('travis@example.com')->first()->ticketQuantity());
    }

    /** @test */
    function cannot_purchase_tickets_to_an_unpublished_concert()
    {
        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);

        $concert = factory(Concert::class)->states('unpublished')->create()->addTickets(2);

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'email' => 'travis@example.com',
            'ticket_quantity' => 2,
            'payment_token' => $paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(404);
        $this->assertFalse($concert->hasOrderFor('travis@example.com'));
        $this->assertEquals(0, $paymentGateway->totalCharges());
    }

    /** @test */
    function an_order_is_not_created_if_payment_fails()
    {
        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);

        $concert = factory(Concert::class)->states('published')->create(['ticket_price' => 3250])->addTickets(3);

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'email' => 'travis@example.com',
            'ticket_quantity' => 3,
            'payment_token' => 'invalid-payment-token',
        ]);

        $response->assertStatus(422);
        $this->assertFalse($concert->hasOrderFor('travis@example.com'));
        $this->assertEquals(3, $concert->ticketsRemaining());
    }

    /** @test */
    function cannot_purchase_more_tickets_than_remain()
    {
        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);

        $concert = factory(Concert::class)->states('published')->create()->addTickets(50);

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'email' => 'travis@example.com',
            'ticket_quantity' => 52,
            'payment_token' => $paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(422);
        $this->assertFalse($concert->hasOrderFor('travis@example.com'));
        $this->assertEquals(50, $concert->ticketsRemaining());


    }

    /** @test */
//    function cannot_purchase_tickets_another_customer_is_already_trying_to_purchase()
//    {
//        $paymentGateway = new FakePaymentGateway;
//        $this->app->instance(PaymentGateway::class, $paymentGateway);
//
//        $concert = factory(Concert::class)->states('published')->create([
//            'ticket_price' => 1200
//        ])->addTickets(3);
//
//        $this->paymentGateway->beforeFirstCharge(function ($paymentGateway) use ($concert) {
//            $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
//                'email' => 'personB@example.com',
//                'ticket_quantity' => 1,
//                'payment_token' => $paymentGateway->getValidTestToken(),
//            ]);
//
//            $response->assertStatus(422);
//            $this->assertFalse($concert->hasOrderFor('personB@example.com'));
//            $this->assertEquals(0, $concert->ticketsRemaining());
//        });
//
//        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
//            'email' => 'personA@example.com',
//            'ticket_quantity' => 3,
//            'payment_token' => $paymentGateway->getValidTestToken(),
//        ]);
//
//        $response->assertStatus(201);
//        $this->assertEquals(3600, $paymentGateway->totalCharges());
//        $this->assertTrue($concert->hasOrderFor('personA@example.com'));
//        $this->assertEquals(3, $concert->ordersFor('personA@example.com')->first()->ticketQuantity());
//    }

    /** @test */
    function email_is_required_to_purchase_tickets()
    {

        $paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $paymentGateway);
        $concert = factory(Concert::class)->states('published')->create()->addTickets(2);

        $response = $this->json('POST', "/concerts/{$concert->id}/orders", [
            'ticket_quantity' => 2,
            'payment_token' => $paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(422);
    }
}
