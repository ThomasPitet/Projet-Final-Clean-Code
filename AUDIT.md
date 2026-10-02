# Audit initial

## 1. Comportement observable

```
PAYMENT stripe_143.82
SQL INSERT booking=1001 total=143.82 status=confirmed
EMAIL lea@example.com: booking 1001 confirmed
TOTAL FINAL: 143.82
```

L'application traite une réservation : elle effectue d'abord un paiement de 143.82 € via Stripe, puis enregistre la réservation n°1001 en base avec le statut « confirmed », envoie un email de confirmation au client, et affiche le total final.

## 2. Problèmes identifiés

| # | Problème | Catégorie | Impact |
|---|---------|-----------|--------|
| 1 | **Classe surchargée `BookingService.confirm()`** — La méthode unique de 50 lignes concentre validation, calcul de prix, paiement, persistance et notification. Elle viole le Single Responsibility Principle. | Responsabilité | Toute modification (ajout de remise, nouveau canal de notification, nouveau moyen de paiement) force à modifier cette unique méthode, augmentant le risque de régression. |
| 2 | **Couplage fort via instanciation directe** — `BookingService` instancie `new StripeClient()` et `new EmailService()` en dur dans `confirm()`. Aucune injection de dépendance. | Couplage | Impossible de substituer un mock en test ou de changer d'implémentation sans modifier `BookingService`. Rend la classe non testable unitairement. |
| 3 | **Règles métier (remises) codées en dur avec magic numbers** — Les remises VIP (`0.90`) et 3 jours (`-10.0`) sont des valeurs littérales noyées dans le flux de contrôle, sans constantes nommées ni encapsulation. | Règles métier | Difficile de comprendre, modifier ou étendre les politiques de remise. Risque élevé d'erreur lors de l'ajout de nouvelles règles tarifaires. |
| 4 | **Strings magiques pour le type client et le moyen de paiement** — `'vip'`, `'standard'`, `'stripe'`, `'payfast'`, `'3days'`, `'day'` sont des chaînes brutes sans énumération. | Lisibilité | Aucune vérification à la compilation/IDE, risque de faute de frappe silencieuse (ex : `'Vip'` au lieu de `'vip'`). Pas de liste exhaustive des valeurs possibles. |
| 5 | **Effets de bord via `echo` pour la persistance et les notifications** — Les appels SQL, paiement et email sont simulés par `echo`. L'absence d'abstraction rend impossible de distinguer un vrai effet de bord d'un log de debug. | Testabilité | Les tests doivent capturer `ob_start()/ob_end_clean()` pour masquer la sortie ; impossible de vérifier qu'un email a été envoyé ou que la BDD a été appelée sans parser `stdout`. |
| 6 | **PayFast déclaré mais non implémenté** — La branche `payfast` dans `confirm()` lève une `RuntimeException('PayFast not implemented')`, alors que le SDK `PayFastSdk` existe et est fonctionnel. | Autre | Fonctionnalité annoncée mais inaccessible. Tout appel avec `payfast` crashe l'application. |

## 3. Nos trois priorités

### Priorité 1 — Classe surchargée `BookingService` (Responsabilité)

**Justification :** C'est le problème structurel le plus bloquant. Toute évolution future (nouveau moyen de paiement, nouvelle remise, nouveau canal de notification) impose de modifier cette unique méthode monolithique. Le risque de régression est maximal car chaque changement touche à tout. Décomposer cette classe est un prérequis pour pouvoir travailler en parallèle sur les autres tickets.

**Risque associé :** Refactorer cette méthode sans filet de tests de caractérisation peut casser le calcul de prix, le paiement ou l'envoi d'email. Les tests existants ne couvrent pas les cas limites (quantité invalide, email invalide, booking vide).

### Priorité 2 — Couplage fort / absence d'injection de dépendance (Couplage)

**Justification :** Le `new StripeClient()` et `new EmailService()` câblés en dur dans `confirm()` empêchent tout test unitaire isolé et tout remplacement d'implémentation. Tant que ce couplage existe, on ne peut pas ajouter PayFast proprement ni vérifier les notifications sans parser `stdout`. L'injection de dépendance est le levier qui rendra le reste du refactoring possible.

**Risque associé :** Introduire l'injection modifie la signature du constructeur ou de la méthode `confirm()`, ce qui impacte tous les appelants (`index.php`, tests).

### Priorité 3 — Règles métier codées en dur (Règles métier)

**Justification :** Les remises VIP et 3 jours sont des valeurs magiques sans nom ni encapsulation. Elles seront amenées à évoluer (le sujet mentionne "ancienne règle"), et leur forme actuelle rend toute modification risquée. Extraire ces règles dans des objets dédiés (stratégie de remise) permet de les tester indépendamment et de les faire évoluer sans toucher au flux de confirmation.

**Risque associé :** Modifier les règles de calcul peut changer silencieusement les montants facturés. Les tests de caractérisation sur les montants exacts sont indispensables avant toute modification.

## 4. Risques avant refactoring

| Risque | Description | Mitigation |
|--------|-------------|------------|
| **Régression sur les montants** | Le calcul total (sous-total, remise VIP, remise 3 jours) est le cœur métier. Une erreur de refactoring peut modifier silencieusement les montants facturés. | Tests de caractérisation couvrant les cas standard, VIP et 3 jours avec vérification des montants exacts. |
| **Perte de l'ordre des opérations** | La séquence paiement → persistance → notification est implicite. Un refactoring maladroit pourrait envoyer un email avant le paiement ou persister avant la confirmation. | Tests de caractérisation vérifiant l'ordre des sorties (PAYMENT avant SQL INSERT avant EMAIL). |
| **Cas d'erreur non documentés** | Les validations (booking vide, email invalide, quantité ≤ 0) lèvent des `RuntimeException` mais ne sont pas testées. Un refactoring pourrait supprimer une validation. | Tests de caractérisation sur tous les cas d'erreur avec vérification du message d'exception. |
| **Casse du contrat PayFastSdk** | `PayFastSdk` ne doit pas être modifié. L'intégration future devra s'adapter à son interface `executePayment(array): array`. | Adapter autour de PayFastSdk pour isoler son contrat. |
| **Services inutilisés masquant des dépendances futures** | `SmsClient`, `LoyaltyService`, `AnalyticsClient` sont peut-être prévus pour des tickets futurs. Les supprimer pourrait être prématuré. | Les conserver mais ne pas les charger tant qu'ils ne sont pas utilisés. |
