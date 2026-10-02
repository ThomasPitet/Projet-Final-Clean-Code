<?php

declare(strict_types=1);

final class SupervisedPaymentGateway implements PaymentGateway
{
    private PaymentGateway $innerGateway;

    public function __construct(PaymentGateway $innerGateway)
    {
        $this->innerGateway = $innerGateway;
    }

    public function pay(float $amount)
    {
        echo "SUPERVISION: Demande de paiement de {$amount} initiée." . PHP_EOL;
        
        $startTime = microtime(true);
        $success = false;

        try {
            $this->innerGateway->pay($amount);
            $success = true;
        } catch (Throwable $e) {
            echo "SUPERVISION: Échec du paiement ({$e->getMessage()})." . PHP_EOL;
            throw $e;
        } finally {
            $endTime = microtime(true);
            $durationMs = round(($endTime - $startTime) * 1000, 2);

            if ($success) {
                echo "SUPERVISION: Paiement réussi en {$durationMs} ms." . PHP_EOL;
            } else {
                echo "SUPERVISION: Paiement échoué en {$durationMs} ms." . PHP_EOL;
            }
        }
    }
}
