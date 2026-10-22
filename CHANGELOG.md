# Changelog

All notable changes to this project are documented here. The format follows Keep a Changelog.

## [0.2.0] — unreleased

### Added

- `Shared\PaymentGatewayInterface`: the provider-neutral way a module takes money. Exact decimal amounts, status only from a fresh read, idempotent start and refund on the caller's key. gCore-Mollie implements it.

### Breaking: the one topology

- Topology discovery in the contract is by name on the one topology; distance and store operations are removed.

