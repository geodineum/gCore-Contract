# Changelog

All notable changes to this project are documented here. The format follows Keep a Changelog.

## [0.2.0] — unreleased

### Added

- `Events\EventStore`, `Events\Replay`, `Events\Ids`, `Events\Sealer`: the event store Planning ran on, shared (namespace a parameter: planning, shop). Personal fields (`sensitive`) leave the process only sealed (`sc`, gs1 to STORE's key via geodineum-seal) when the node has STORE's key, with `sj` naming the subject; a seal that fails refuses the write.
- `Events\Sealer` runs geodineum-seal from `/usr/local/lib/geodineum/geodineum-seal` (root-installed, never a build tree) and only when root owns the tool and its directory and neither is writable by group or others; `GEODINEUM_SEAL_BIN` gets the same check. Otherwise the seal fails and the write is refused. Tests: `php tests/SealerTest.php`.
- `Premium\MollieManagerInterface`: `setMode('live')` is refused without the payee pin; a live key and live mode are production only (the tier from the host or its gNode client, never stored config), and `getCredentialStatus` names the tier (`environment`).
- `Premium\MollieManagerInterface`: the payee is pinned by the host's code (`payee_profile`), a payment in another profile is never settled, live without a pin takes no payment, `getCredentialStatus` names the pin; `PaymentGatewayInterface::startPayment`'s checkout_url is only the provider's own https host.
- `Shared\PaymentGatewayInterface`: the provider-neutral way a module takes money. Exact decimal amounts, status only from a fresh read, idempotent start and refund on the caller's key. gCore-Mollie implements it.
- `PaymentGatewayInterface::startPayment` documents optional `lines`, `method`, `locale`, `billing_address`, `shipping_address`; `idempotency_key` is required (start and refund refuse an empty key).

### Breaking: the one topology

- Topology discovery in the contract is by name on the one topology; distance and store operations are removed.

