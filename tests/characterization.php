<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestRunner.php';

$tests = new TestRunner();

function createBooking(
    string $customerType = 'standard',
    string $passType = 'day',
    float $price = 50.0,
    int $quantity = 1,
    ?string $phone = '0600000000'
): Booking {
    $customer = new Customer(1, 'test@example.com', $phone, $customerType);
    $ticket = new Ticket('TEST', 'Ticket test', $price);
    $booking = new Booking(1, $customer, $passType);
    $booking->addItem(new BookingItem($ticket, $quantity));
    return $booking;
}

$service = new BookingService();

// ============================================================
// SECTION 1 : Règles de calcul de prix (caractérisation)
// ============================================================

ob_start();
$standard = createBooking('standard', 'day', 50.0, 2);
$standardTotal = $service->confirm($standard, new StripeAdapter());
ob_end_clean();
$tests->near(100.0, $standardTotal, 'standard customer keeps initial total');
$tests->same('confirmed', $standard->status, 'booking becomes confirmed');

ob_start();
$vip = createBooking('vip', 'day', 45.0, 2);
$vipTotal = $service->confirm($vip, new StripeAdapter());
ob_end_clean();
$tests->near(85.5, $vipTotal, 'legacy VIP rule gives 5 percent discount when total is under 100');

ob_start();
$vip = createBooking('vip', 'day', 50.0, 2);
$vipTotal = $service->confirm($vip, new StripeAdapter());
ob_end_clean();
$tests->near(90.0, $vipTotal, 'legacy VIP rule gives 10 percent discount when total is between 100 and 299.99');

ob_start();
$vip = createBooking('vip', 'day', 250.0, 2);
$vipTotal = $service->confirm($vip, new StripeAdapter());
ob_end_clean();
$tests->near(425.0, $vipTotal, 'legacy VIP rule gives 15 percent discount when total is more than 300');

ob_start();
$threeDays = createBooking('standard', '3days', 60.0, 2);
$threeDaysTotal = $service->confirm($threeDays, new StripeAdapter());
ob_end_clean();
$tests->near(100.0, $threeDaysTotal, 'legacy three day pass discount is 20 euros');

// VIP + 3 jours combinés
ob_start();
$vipThreeDays = createBooking('vip', '3days', 100.0, 1);
$vipThreeDaysTotal = $service->confirm($vipThreeDays, new StripeAdapter());
ob_end_clean();
// 100 * 0.90 = 90 - 20 = 70
$tests->near(70.0, $vipThreeDaysTotal, 'VIP + 3days: VIP discount applied first then 3days discount');

// Ticket unique sans remise
ob_start();
$single = createBooking('standard', 'day', 25.0, 1);
$singleTotal = $service->confirm($single, new StripeAdapter());
ob_end_clean();
$tests->near(25.0, $singleTotal, 'single ticket no discount');

// ============================================================
// SECTION 2 : Ordre des effets de bord (caractérisation)
// ============================================================

ob_start();
$orderBooking = createBooking('standard', 'day', 40.0, 1);
$service->confirm($orderBooking, new StripeAdapter());
$output = ob_get_clean();

$lines = array_values(array_filter(array_map('trim', explode("\n", $output))));

$tests->same(true, str_starts_with($lines[0] ?? '', 'PAYMENT'), 'first output is PAYMENT');
$tests->same(true, str_starts_with($lines[1] ?? '', 'SQL INSERT'), 'second output is SQL INSERT');
$tests->same(true, str_starts_with($lines[2] ?? '', 'EMAIL'), 'third output is EMAIL');
$tests->same(6, count($lines), 'exactly 6 output lines for standard booking');

// ============================================================
// SECTION 3 : Contenu des sorties (caractérisation)
// ============================================================

ob_start();
$outputBooking = createBooking('standard', 'day', 50.0, 2);
$outputTotal = $service->confirm($outputBooking, new StripeAdapter());
$outputStr = ob_get_clean();

$tests->same(true, str_contains($outputStr, 'stripe_100.00'), 'payment output contains stripe transaction id with amount');
$tests->same(true, str_contains($outputStr, 'booking=1 total=100'), 'SQL output contains booking id and total');
$tests->same(true, str_contains($outputStr, 'status=confirmed'), 'SQL output contains confirmed status');
$tests->same(true, str_contains($outputStr, 'EMAIL test@example.com: booking 1 confirmed'), 'email output contains correct email and booking id');

// ============================================================
// SECTION 4 : Cas d'erreur (caractérisation)
// ============================================================

// Booking vide
$emptyBookingThrown = false;
try {
    $emptyBooking = new Booking(99, new Customer(1, 'a@b.com'), 'day');
    $service->confirm($emptyBooking, new StripeAdapter());
} catch (RuntimeException $e) {
    $emptyBookingThrown = ($e->getMessage() === 'Empty booking');
}
$tests->same(true, $emptyBookingThrown, 'empty booking throws RuntimeException');

// Email invalide
$invalidEmailThrown = false;
try {
    $badCustomer = new Customer(1, 'not-an-email', null, 'standard');
    $badBooking = new Booking(99, $badCustomer, 'day');
    $badBooking->addItem(new BookingItem(new Ticket('T', 'T', 10.0), 1));
    ob_start();
    $service->confirm($badBooking, new StripeAdapter());
    ob_end_clean();
} catch (RuntimeException $e) {
    $invalidEmailThrown = ($e->getMessage() === 'Invalid email');
}
$tests->same(true, $invalidEmailThrown, 'invalid email throws RuntimeException');

// Quantité invalide
$invalidQtyThrown = false;
try {
    $zeroQtyBooking = createBooking('standard', 'day', 50.0, 0);
    ob_start();
    $service->confirm($zeroQtyBooking, new StripeAdapter());
    ob_end_clean();
} catch (RuntimeException $e) {
    $invalidQtyThrown = ($e->getMessage() === 'Invalid quantity');
}
$tests->same(true, $invalidQtyThrown, 'zero quantity throws RuntimeException');

// Moyen de paiement inconnu
$unknownPaymentThrown = false;
try {
    ob_start();
    $unknownPayBooking = createBooking('standard', 'day', 50.0, 1);
    $service->confirm($unknownPayBooking, 'bitcoin');
    ob_end_clean();
} catch (RuntimeException $e) {
    $unknownPaymentThrown = ($e->getMessage() === 'Unknown payment method');
}
$tests->same(true, $unknownPaymentThrown, 'unknown payment method throws RuntimeException');

// PayFast implémenté
ob_start();
$payfastBooking = createBooking('standard', 'day', 50.0, 1);
$payfastTotal = $service->confirm($payfastBooking, new PayFastAdapter());
$payfastOutput = ob_get_clean();

$tests->near(50.0, $payfastTotal, 'payfast total is correct');
$tests->same(true, str_contains($payfastOutput, 'PAYMENT payfast_'), 'payfast output contains payfast transaction id');

// ============================================================
// SECTION 5 : Scénario index.php (caractérisation exacte)
// ============================================================

ob_start();
$leaCustomer = new Customer(42, 'lea@example.com', '0612345678', 'vip');
$dayTicket = new Ticket('DAY-1', 'Pass Jour 1', 79.90);
$leaBooking = new Booking(1001, $leaCustomer, 'day');
$leaBooking->addItem(new BookingItem($dayTicket, 2));
$leaTotal = $service->confirm($leaBooking, new StripeAdapter());
$leaOutput = ob_get_clean();

$tests->near(143.82, $leaTotal, 'index.php scenario: total is 143.82');
$tests->same(true, str_contains($leaOutput, 'PAYMENT stripe_143.82'), 'index.php scenario: payment line correct');
$tests->same(true, str_contains($leaOutput, 'SQL INSERT booking=1001 total=143.82 status=confirmed'), 'index.php scenario: SQL line correct');
$tests->same(true, str_contains($leaOutput, 'EMAIL lea@example.com: booking 1001 confirmed'), 'index.php scenario: email line correct');

$tests->summary();
