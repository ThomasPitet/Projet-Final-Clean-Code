<?php

const DISCOUNT_WHEN_UNDER_100 = 0.95;
const DISCOUNT_WHEN_BETWEEN_100_AND_300 = 0.90;
const DISCOUNT_WHEN_MORE_THAN_300 = 0.85;
const DISCOUNT_3DAYS = 20.0;

class DiscountCalculator {


    public function calculateTotalDiscount(float $total, Customer $customer, string $passType) {

        if($customer->isVip() && $total < 100) $total *= DISCOUNT_WHEN_UNDER_100;                   //remise 5 %

        $isBetween100And300 = 100 <= $total && $total < 300;
        if($customer->isVip() && $isBetween100And300) $total *= DISCOUNT_WHEN_BETWEEN_100_AND_300;  //remise 10 %

        if($customer->isVip() && $total >= 300) $total *= DISCOUNT_WHEN_MORE_THAN_300;              //remise 15 %

        if ($passType === '3days') $total -= DISCOUNT_3DAYS;

        if($total < 0) $total = 0;
    
        return $total;
    }
}