<?php

class PayFastAdapter implements PaymentGateway {
    public function pay(float $amount){
        $payFastSdk = new PayFastSdk();
        $payload = [
            'reference' => (string) $amount,
            'amount_cents' => (int) round($amount * 100),
            'currency' => 'EUR'
        ];
        $transaction = $payFastSdk->executePayment($payload);
        if ($transaction['success']) {
            $id = $transaction['transaction_id'];
            echo "PAYMENT {$id}" . PHP_EOL;
        }
    }
}