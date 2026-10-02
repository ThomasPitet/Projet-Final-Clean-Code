<?php

declare(strict_types=1);

final class BookingService
{
    public function confirm(Booking $booking, mixed $paymentMethod = null): float
    {
        if ($paymentMethod === null) {
            $paymentMethod = new StripeAdapter();
        }
        $customer = $booking->customer;

        if (count($booking->items) === 0) {
            throw new RuntimeException('Empty booking');
        }

        if (!filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email');
        }

        $total = 0.0;

        foreach ($booking->items as $item) {
            if ($item->quantity <= 0) {
                throw new RuntimeException('Invalid quantity');
            }

            $total += $item->ticket->price * $item->quantity;
        }

        // Logique des réductions
        $total = (new DiscountCalculator())->calculateTotalDiscount($total, $customer, $booking->passType);
        
        if (!$paymentMethod instanceof PaymentGateway) {
            throw new RuntimeException('Unknown payment method');
        }

        $booking->status = 'confirmed';

        $paymentMethod->pay($total);

        echo "SQL INSERT booking={$booking->id} total={$total} status={$booking->status}" . PHP_EOL;

        $clientService = new ClientService();
        $clientService->service($booking, $customer, $total);

        return $total;
    }
}