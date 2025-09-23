# Smoke Test Scenario

This document describes the expected outcome of a manual smoke test for the multi-place booking and payout flow.

## Scenario

1.  **A passenger (P) books 2 places for a trip with price P per place.**
    *   User (P) balance: `credits = credits - (2 * P)`
    *   Site wallet balance: `balance = balance + (2 * P)`
    *   A `participation_pay` transaction is created (debit user, credit site wallet).
    *   The number of available places for the trip is decreased by 2: `places_disponibles = places_disponibles - 2`.

2.  **The driver starts the trip.**
    *   A `trip_started` event is recorded in the `transactions` table (with `amount=0`).

3.  **The driver marks the trip as arrived.**
    *   A `trip_arrived` event is recorded in the `transactions` table (with `amount=0`).
    *   The trip status is updated: `voyages.statut = 'valide'`.

4.  **The payout job is executed for the trip.**
    *   Driver's balance: `credits = credits + (2 * P - 2 * 2)` (payout - commission).
    *   Site wallet balance: `balance = balance - (2 * P - 2 * 2)`.
    *   A `site_commission` transaction is created with amount `2 * 2`.
    *   A `driver_payout` transaction is created.
