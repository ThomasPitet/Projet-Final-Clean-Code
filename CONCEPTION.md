# Note de conception

## 1. Choix principaux

Le projet sépare le traitement d'une réservation en quelques responsabilités :

- `BookingService` valide la réservation, calcule son total, demande le paiement, puis déclenche l'enregistrement et les notifications.
- `DiscountCalculator` concentre les règles de remise VIP et de forfait trois jours.
- `PaymentGateway` définit le contrat commun utilisé par `BookingService`. `StripeAdapter` et `PayFastAdapter` relient ce contrat aux fournisseurs de paiement.
- `ClientService` regroupe les effets destinés au client et au suivi : confirmation par email, points de fidélité, SMS facultatif et événement analytics.
- `SupervisedPaymentGateway` ajoute la journalisation du montant, du résultat et de la durée autour d'une passerelle sans modifier le fournisseur sous-jacent.

## 2. Principes SOLID mobilisés

- **SRP :** `DiscountCalculator`, les adaptateurs et `ClientService` isolent respectivement les remises, les fournisseurs de paiement et les effets client. La séparation reste partielle : `BookingService` orchestre encore plusieurs étapes.
- **OCP / LSP :** `StripeAdapter`, `PayFastAdapter` et `SupervisedPaymentGateway` partagent le contrat `PaymentGateway`. On peut substituer ou ajouter une passerelle sans exposer son SDK au service métier.
- **DIP :** `BookingService` dépend de `PaymentGateway` plutôt que directement de Stripe ou PayFast. Le principe reste partiel : Stripe est instancié par défaut et `ClientService` construit ses propres dépendances.

ISP n'est pas revendiqué : le projet ne définit pas d'interfaces fines pour les autres effets externes.

## 3. Design Patterns éventuellement utilisés

- **Adapter :** `StripeAdapter` et `PayFastAdapter` traduisent les contrats différents des SDK vers `PaymentGateway`. Cela évite de coupler `BookingService` aux fournisseurs et de modifier leurs clients pour la supervision.
- **Strategy :** `PaymentGateway` permet de fournir à `BookingService` un moyen de paiement interchangeable. Une condition dédiée à chaque fournisseur serait plus courte au départ, mais couplerait le service métier à leurs détails.
- **Decorator :** `SupervisedPaymentGateway` enveloppe une passerelle pour journaliser montant, résultat et durée sans toucher aux règles métier ni aux SDK. Une stratégie dédiée aux remises n'est pas utile avec le nombre actuel de règles ; `DiscountCalculator` les applique directement.

## 4. Solutions envisagées puis écartées

- **Appeler Stripe ou PayFast directement depuis `BookingService` :** écarté pour éviter de coupler l'orchestration métier aux SDK et à leurs contrats distincts.
- **Ajouter la supervision dans chaque SDK ou adaptateur :** écarté car cela dupliquerait une préoccupation transversale et violerait la contrainte de ne pas modifier les SDK pour ce besoin.
- **Ajouter une condition par fournisseur dans `BookingService` :** écarté au profit du contrat `PaymentGateway`, qui laisse le service travailler avec une abstraction.
- **Créer une stratégie distincte pour chaque remise dès maintenant :** non retenu, car le nombre de règles ne justifie pas encore ce niveau de découpage.
- **Introduire un framework ou des interfaces pour chaque effet externe :** non retenu dans le périmètre actuel, volontairement sans dépendance. Cette simplicité implique que les appels sont simulés et que plusieurs dépendances ne sont pas substituables en test.

## 5. Ce que nous améliorerions avec plus de temps

- Injecter les services de paiement, de notification, de fidélité, d'analytics et de persistance au lieu de les instancier directement dans les services.
- Définir un contrat de paiement plus explicite : type de retour, erreurs attendues, devise et représentation monétaire. Utiliser un type décimal adapté à l'argent plutôt que des `float` pour éviter les imprécisions binaires.
- Clarifier et centraliser les règles métier (seuils et taux de remise), puis extraire des stratégies si leur évolution le justifie.
- Décider explicitement du comportement transactionnel lorsqu'un paiement réussit mais que l'enregistrement ou une notification échoue.
- Uniformiser les types de retour, les types de paramètres et le formatage de l'ensemble des classes.
