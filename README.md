# gCore Contracts

Interface contracts for gCore premium managers. This package defines the contracts that premium managers must implement, enabling:

- **Graceful degradation**: Free-tier stubs implement the same interfaces
- **Third-party implementations**: Anyone can create compatible managers
- **Type safety**: Strong contracts for dependency injection
- **Decoupling**: Premium packages can depend on contracts, not implementations

## Installation

```bash
composer require nierto/gcore-contracts
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

MIT License - see [LICENSE](LICENSE) for details.

## Related Packages

- `nierto/gcore` - Full gCore framework
- `nierto/gcore-stubs` - Free-tier stub implementations
- `nierto/gsd-client` - GSD (Geometric Service Daemon) client
