<?php

class DiscountCalculator {
    public function calculateTotalDiscount(float $total, Customer $customer, string $passType) {

        if($customer->isVip() && $total < 100) $total *= 0.95;                          //remise 5 %

        $isBetween100And300 = 100 <= $total && $total < 300;
        if($customer->isVip() && $isBetween100And300) $total *= 0.90;                   //remise 10 %

        if($customer->isVip() && $total >= 300) $total *= 0.85;                         //remise 15 %

        if ($passType === '3days') $total -= 20.0;

        if($total < 0) $total = 0;
    
        return $total;
    }
}