<?php

namespace App\Billing;

interface PaymentGateway
{
    public function charge(int $amount, $token);

    public function getValidTestToken();

    public function newChargesDuring($callback);
}
