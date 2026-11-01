import type { Transaction } from './types';

type StatusFields = Pick<Transaction, 'isPending' | 'status'>;

export function isActiveTransaction(tx: StatusFields): boolean {
    return tx.status !== 'history';
}

export function isBookedTransaction(tx: StatusFields): boolean {
    return isActiveTransaction(tx) && !tx.isPending && tx.status !== 'reserved';
}

export function transactionStatusLabel(tx: StatusFields): string {
    return !isActiveTransaction(tx)
        ? 'Historie'
        : isBookedTransaction(tx)
          ? 'Geboekt'
          : 'Reservering';
}

export function transactionStatusTotals(transactions: Transaction[]) {
    const result = {
        bookedIn: 0,
        bookedOut: 0,
        reservedIn: 0,
        reservedOut: 0,
        reservedCount: 0,
    };
    for (const tx of transactions) {
        if (!isActiveTransaction(tx)) continue;
        const booked = isBookedTransaction(tx);
        if (!booked) result.reservedCount++;
        const field = booked
            ? tx.amount >= 0
                ? 'bookedIn'
                : 'bookedOut'
            : tx.amount >= 0
              ? 'reservedIn'
              : 'reservedOut';
        result[field] += Math.round(Math.abs(tx.amount) * 100);
    }
    return {
        ...result,
        bookedIn: result.bookedIn / 100,
        bookedOut: result.bookedOut / 100,
        reservedIn: result.reservedIn / 100,
        reservedOut: result.reservedOut / 100,
    };
}

export function transactionArchiveReason(reason?: string | null): string {
    return (
        (
            {
                reservation_finalized: 'Reservering is definitief geboekt',
                reservation_replaced:
                    'Reservering vervangen door de actieve boeking',
                reservation_updated: 'Reservering bijgewerkt door de bank',
                confirmed_duplicate: 'Bevestigde dubbele registratie',
                reservation_matched: 'Reservering na controle gekoppeld aan definitieve betaling',
                reservation_expired: 'Reservering na controle als vervallen gemarkeerd',
                links_before_reservation_match: 'Koppelingen vóór beoordeling van de reservering',
            } as Record<string, string>
        )[reason ?? ''] ?? 'Historische registratie'
    );
}
