<?php

interface PaymentGateway {
    public function pay(float $amount);
}