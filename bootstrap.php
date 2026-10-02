<?php

declare(strict_types=1);

// Models
require_once __DIR__ . '/src/Models/Customer.php';
require_once __DIR__ . '/src/Models/Ticket.php';
require_once __DIR__ . '/src/Models/BookingItem.php';
require_once __DIR__ . '/src/Models/Booking.php';

// Services
require_once __DIR__ . '/src/Services/DiscountCalculator.php';
require_once __DIR__ . '/src/Services/LoyaltyService.php';
require_once __DIR__ . '/src/Services/BookingService.php';

// Infrastructure
require_once __DIR__ . '/src/Infrastructure/PaymentGateway.php';
require_once __DIR__ . '/src/Infrastructure/StripeClient.php';
require_once __DIR__ . '/src/Infrastructure/StripeAdapter.php';
require_once __DIR__ . '/src/Infrastructure/PayFastSdk.php';
require_once __DIR__ . '/src/Infrastructure/PayFastAdapter.php';
require_once __DIR__ . '/src/Infrastructure/EmailService.php';
require_once __DIR__ . '/src/Infrastructure/SmsClient.php';
require_once __DIR__ . '/src/Infrastructure/AnalyticsClient.php';
