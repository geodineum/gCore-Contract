<?php
declare(strict_types=1);

namespace gCore\Contracts\Shared;

/**
 * What happened to a payment's money after it was paid, read from the gateway's own record
 * (kept current by its webhook; no call out). Refunds and chargebacks leave a payment `paid`,
 * so a module that invoices reads this to issue credit notes.
 *
 * Optional beside PaymentGatewayInterface: a gateway that cannot tell returns nothing and the
 * module treats refunds as manual.
 */
interface PaymentLedgerInterface
{
    /**
     * Exact decimal strings in the payment's currency, "0.00" when there is none.
     *
     * @return array{status:string, currency:string, amount:string, refunded:string, charged_back:string}|null
     *         null when the payment is unknown here
     */
    public function paymentMoney(string $siteId, string $paymentId): ?array;
}
