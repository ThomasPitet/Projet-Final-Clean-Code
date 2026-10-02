<?php

class ClientService {
    public function service(Booking $booking, Customer $customer, float $total){
       
        $emailService = new EmailService();
        $emailService->sendConfirmation($customer->email, $booking->id);
        
        $loyaltyService = new LoyaltyService();
        $loyaltyService->addPoints($customer->id, (int) $total/4);

        $smsClient = new SmsClient();
        if($customer->phone != null) $smsClient->send($customer->phone, "Votre paiement a bien été validé.");

        $bookingItems = [];
        foreach($booking->items as $item){
            $ticket = $item->ticket;
            $bookingItems[] = [
                "ticket" => [
                    "code" => $ticket->code,
                    "label" => $ticket->label,
                    "price" => $ticket->price
                ],
                "quantity" => $item->quantity
            ];
        }
        $analyticsClient = new AnalyticsClient();
        $analyticsClient->track("booking completed", 
        [
            "customer" => [
                "id" => $customer->id,
                "email" => $customer->email,
                "phone" => $customer->phone,
                "type" => $customer->type
            ],
            "booking" => [
                "status" => $booking->status,
                "passType" => $booking->passType,
                "bookingItems" => $bookingItems
            ]
        ]);
    }
}