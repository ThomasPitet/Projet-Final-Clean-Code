<?php

declare(strict_types=1);

final class BookingService
{
    public function confirm(Booking $booking, string $paymentMethod = 'stripe'): float
    {
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

        if ($paymentMethod === 'stripe') {
            $stripe = new StripeClient();
            $transactionId = $stripe->charge($total);
            echo "PAYMENT {$transactionId}" . PHP_EOL;
        } elseif ($paymentMethod === 'payfast') {
            throw new RuntimeException('PayFast not implemented');
        } else {
            throw new RuntimeException('Unknown payment method');
        }

        $booking->status = 'confirmed';

        echo "SQL INSERT booking={$booking->id} total={$total} status={$booking->status}" . PHP_EOL;

        $emailService = new EmailService();
        $emailService->sendConfirmation($customer->email, $booking->id);

        return $total;
    }
}