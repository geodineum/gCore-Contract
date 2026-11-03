# Changelog

All notable changes to this project are documented here. The format follows Keep a Changelog.

## [0.2.0] — unreleased

### Added

- `Events\EventStore`, `Events\Replay`, `Events\Ids`, `Events\Sealer`: the event store Planning ran on, shared (namespace a parameter: planning, shop). Personal fields (`sensitive`) leave the process only sealed (`sc`, gs1 to STORE's key via geodineum-seal) when the node has STORE's key, with `sj` naming the subject; a seal that fails refuses the write.
- `Shared\PaymentGatewayInterface`: the provider-neutral way a module takes money. Exact decimal amounts, status only from a fresh read, idempotent start and refund on the caller's key. gCore-Mollie implements it.
- `PaymentGatewayInterface::startPayment` documents optional `lines`, `method`, `locale`, `billing_address`, `shipping_address`; `idempotency_key` is required (start and refund refuse an empty key).

### Breaking: the one topology

- Topology discovery in the contract is by name on the one topology; distance and store operations are removed.

