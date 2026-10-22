<?php
declare(strict_types=1);
/**
 * PaymentGatewayInterface
 *
 * The provider-neutral way a gCore module takes money. A consumer (planning,
 * a shop) depends on this and nothing else, so a site swaps providers by
 * configuration and a new provider is one adapter. Mollie's semantics set the
 * floor every implementation meets:
 *
 * - An amount is an exact decimal string with its currency, never a float.
 * - A payment's state is what the provider says on a fresh read, never what a
 *   redirect or a webhook body claims. Consumers poll paymentStatus(); the
 *   implementation keeps that answer current from its own webhook.
 * - Starting a payment is idempotent on the caller's key: a retried request
 *   returns the same payment, never a second charge.
 *
 * Discovery: an implementation is a singleton (ModuleInterface::getInstance).
 * A consumer names the class in its site config (key `payment_gateway`) and
 * checks `instanceof PaymentGatewayInterface` before use.
 *
 * @package     gCore
 * @subpackage  Contracts\Shared
 * @version     1.0.0
 */

namespace gCore\Contracts\Shared;

interface PaymentGatewayInterface
{
    /** Statuses every implementation maps onto. paid is the only one that means money arrived. */
    public const STATUSES = ['open', 'pending', 'authorized', 'paid', 'canceled', 'expired', 'failed'];

    /** A short stable name, e.g. 'mollie'. Stored beside each payment id. */
    public function gatewayId(): string;

    /**
     * Whether this site can take a payment now (credential present for the
     * active mode). A consumer refuses to sell rather than start a payment
     * that cannot be completed.
     *
     * @return array{ready:bool, mode:string, reason:?string} mode: 'test' | 'live' | 'unconfigured'
     */
    public function readiness(string $siteId): array;

    /**
     * Start a payment and return where the customer pays.
     *
     * $request:
     *   amount          {currency: 'EUR', value: '12.50'}  (exact decimal string)
     *   description     what the cardholder sees on their statement (max 255)
     *   return_url      where the customer comes back to
     *   idempotency_key the caller's key; the same key returns the same payment
     *   metadata        optional, small, no personal data beyond an order id
     *
     * @return array{ok:bool, id:?string, status:?string, checkout_url:?string, error:?string}
     */
    public function startPayment(string $siteId, array $request): array;

    /** One of STATUSES, or null when the id is unknown to this gateway. */
    public function paymentStatus(string $siteId, string $paymentId): ?string;

    /**
     * Refund part or all of a paid payment. The same idempotency key never refunds twice.
     *
     * @param array{currency:string, value:string}|null $amount null refunds the rest
     * @return array{ok:bool, error:?string}
     */
    public function refund(string $siteId, string $paymentId, ?array $amount, string $description, string $idempotencyKey): array;
}
