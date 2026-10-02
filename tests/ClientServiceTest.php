<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

function createTestBooking(
    string $customerType = 'standard',
    string $passType = 'day',
    float $price = 50.0,
    int $quantity = 2,
    ?string $phone = '0600000000'
): Booking {
    $customer = new Customer(1, 'test@example.com', $phone, $customerType);
    $ticket = new Ticket('TEST', 'Ticket test', $price);
    $booking = new Booking(1, $customer, $passType);
    $booking->status = 'confirmed';
    $booking->addItem(new BookingItem($ticket, $quantity));
    return $booking;
}

$clientService = new ClientService();

// Test 1: Service exécute toutes les actions correctement (avec téléphone)
ob_start();
$booking = createTestBooking('standard', 'day', 50.0, 2, '0612345678');
// Total is 100
$clientService->service($booking, $booking->customer, 100.0);
$output = ob_get_clean();

$tests->same(true, str_contains($output, "EMAIL test@example.com: booking 1 confirmed"), "Envoi de l'email de confirmation");
$tests->same(true, str_contains($output, "LOYALTY customer=1 points=25"), "Ajout des points de fidélité (100 / 4 = 25)");
$tests->same(true, str_contains($output, "SMS 0612345678: Votre paiement a bien été validé."), "Envoi du SMS");
$tests->same(true, str_contains($output, "ANALYTICS booking completed"), "Appel analytics de base");
$tests->same(true, str_contains($output, '"email":"test@example.com"'), "Analytics contient les données client");
$tests->same(true, str_contains($output, '"price":50'), "Analytics contient les données de réservation");

// Test 2: Service exécute les actions (sans téléphone, pas de SMS)
ob_start();
$bookingNoPhone = createTestBooking('standard', 'day', 60.0, 1, null);
// Total is 60
$clientService->service($bookingNoPhone, $bookingNoPhone->customer, 60.0);
$outputNoPhone = ob_get_clean();

$tests->same(true, str_contains($outputNoPhone, "EMAIL test@example.com: booking 1 confirmed"), "Envoi de l'email de confirmation (sans tel)");
$tests->same(true, str_contains($outputNoPhone, "LOYALTY customer=1 points=15"), "Ajout des points de fidélité (60 / 4 = 15)");
$tests->same(false, str_contains($outputNoPhone, "SMS"), "Pas d'envoi de SMS si numéro nul");
$tests->same(true, str_contains($outputNoPhone, "ANALYTICS booking completed"), "Appel analytics avec json");

$tests->summary();
