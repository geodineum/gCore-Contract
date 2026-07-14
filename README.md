<p align="center">
  <a href="https://geodineum.com">
    <img src=".github/geodineum-logo.png" alt="Geodineum" width="128">
  </a>
</p>

# gCore Contracts

Interface contracts for gCore premium managers. This package defines the contracts that premium managers must implement, enabling:

- **Graceful degradation**: Free-tier stubs implement the same interfaces
- **Third-party implementations**: Anyone can create compatible managers
- **Type safety**: Strong contracts for dependency injection
- **Decoupling**: Premium packages can depend on contracts, not implementations

## Installation

```bash
composer require geodineum/gcore-contract
```

## Available Interfaces

### Premium Manager Interfaces

| Interface | Purpose |
|-----------|---------|
| `MetricsManagerInterface` | Performance metrics tracking |
| `StateManagerInterface` | Distributed state management |
| `ManifestManagerInterface` | PWA manifest generation |
| `AnalyticsManagerInterface` | Privacy-first analytics |
| `InferenceManagerInterface` | AI/ML inference |
| `TopologyManagerInterface` | Service mesh discovery |
| `SEOManagerInterface` | SEO and GEO/AIO features |
| `OptimizationManagerInterface` | Performance optimization |
| `TemplateManagerInterface` | Template rendering (Tera) |
| `CommsManagerInterface` | Multi-channel notifications |

### Shared Interfaces

| Interface | Purpose |
|-----------|---------|
| `ModuleInterface` | Base interface for all managers |

## Usage

### Implementing a Manager

```php
use gCore\Contracts\Premium\MetricsManagerInterface;
use gCore\Contracts\Shared\ModuleInterface;

class MyMetricsManager implements MetricsManagerInterface
{
    public function trackMetric(string $name, $value, array $tags = []): bool
    {
        // Your implementation
    }

    // ... implement all interface methods
}
```

### Type-Hinting

```php
use gCore\Contracts\Premium\AnalyticsManagerInterface;

class MyService
{
    public function __construct(
        private AnalyticsManagerInterface $analytics
    ) {}

    public function trackPageView(): void
    {
        $this->analytics->trackVisit([
            'page' => $_SERVER['REQUEST_URI'],
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
        ]);
    }
}
```

## Interface Contract Details

All premium interfaces extend `ModuleInterface`, which provides:

```php
interface ModuleInterface
{
    public static function getInstance(): ModuleInterface;
    public function initialize(array $config = []): void;
    public function shutdown(): void;
    public function isInitialized(): bool;
    public function getStatus(): array;
    public function getConfig(): array;
    public function updateConfig(array $config): void;
    public function getCapabilityVector(): array;
}
```

## Versioning

This package follows semantic versioning:

- **Major**: Breaking changes to interface signatures
- **Minor**: New methods added to interfaces (with default implementations in stubs)
- **Patch**: Documentation, bug fixes

## License

Licensed under either of

* [Apache License, Version 2.0](LICENSE-APACHE)
* [MIT License](LICENSE-MIT)

at your option.

## Part of the Geodineum ecosystem

This package is the contract layer of [gCore](https://github.com/geodineum/gCore),
the PHP manager-of-managers framework in the
[Geodineum](https://github.com/geodineum) ecosystem. Free-tier Stubs inside
gCore and the Pro implementations both satisfy these interfaces, so callers
consume the interface and never care which one is installed. Build your own
implementation against these contracts and gCore's resolver slots it in with
no caller changes.
