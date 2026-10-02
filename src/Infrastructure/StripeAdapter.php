<?php

class StripeAdapter implements PaymentGateway {
    public function pay(float $amount){
        $stripeClient = new StripeClient();
        $transactionId = $stripeClient->charge($amount);
        echo "PAYMENT {$transactionId}" . PHP_EOL;
    }
}