<?php
/**
 * TopologyManager Interface
 *
 * Contract for service mesh topology visualization and management
 * using gNode's geometric discovery capabilities.
 *
 * Premium implementations provide:
 * - Service mesh visualization in 3D space
 * - One topology per constellation: 24 slots per shape, axes declared by name
 * - Smart registration with hash-based idempotency
 * - Geometric service discovery
 * - Custom dimension registration
 * - Real-time service relationships
 *
 * Free-tier stubs provide graceful no-op degradation.
 *
 * @package     gCore\Contracts
 * @subpackage  Premium
 * @version     1.0.0
 * @since       3.0.0
 */

namespace gCore\Contracts\Premium;

use gCore\Contracts\Shared\ModuleInterface;


/**
 * Interface TopologyManagerInterface
 *
 * Defines the contract for service mesh topology operations.
 * Implementations may use gNode for geometric discovery (premium) or
 * provide no-op stubs for graceful degradation (free tier).
 */
interface TopologyManagerInterface extends ModuleInterface
{
    // =========================================================================
    // GEOMETRIC DISCOVERY METHODS
    // =========================================================================

    /**
     * Discover services on the one topology by the words of their declaration
     *
     * @param array $requirements Axis name => word (facet), list of words (any of) or [lo, hi] range
     * @param int $limit Maximum number of services to return
     * @param string $layer Shape word (site, app, daemon, component, node) or layer key; '3' = every service
     * @return array Records with service_id, shape, environment, colour, metadata
     */
    public function discoverServices(array $requirements, int $limit = 10, string $layer = '3'): array;

    /**
     * Find services matching requirements
     *
     * @param array $requirements Axis name => word | words | range
     * @return array Records with service_id, shape, environment, colour, metadata
     */
    public function findServices(array $requirements): array;

    /**
     * Get service details
     *
     * @param string $serviceId Service identifier
     * @return array Service details including capabilities and metadata
     */
    public function getServiceDetails(string $serviceId): array;

    /**
     * Get optimal load sequence for services
     *
     * @param string $group Service group name
     * @return array Ordered service list
     */
    public function getLoadSequence(string $group = 'default'): array;

    // =========================================================================
    // DIMENSION MANAGEMENT
    // =========================================================================

    /**
     * Register custom dimension
     *
     * @param string $name Dimension name
     * @param array $config Dimension configuration (label, type, min, max, unit)
     * @return bool Success status
     */
    public function registerDimension(string $name, array $config = []): bool;

    /**
     * Get all registered dimensions
     *
     * @return array Dimensions with metadata
     */
    public function getDimensions(): array;

    /**
     * Get dimension by name
     *
     * @param string $name Dimension name
     * @return array|null Dimension config or null
     */
    public function getDimension(string $name): ?array;

    /**
     * Get capability dimensions from gNode
     *
     * @return array gNode capability dimensions
     */
    public function getCapabilityDimensions(): array;

    // =========================================================================
    // TOPOLOGY VISUALIZATION
    // =========================================================================

    /**
     * Get topology data for 3D visualization
     *
     * @param array $selectedDimensions Dimensions to visualize ['x' => dim, 'y' => dim, 'z' => dim]
     * @param array $filters Optional filters
     * @return array Topology visualization data with nodes, edges, and statistics
     */
    public function getTopologyVisualization(array $selectedDimensions, array $filters = []): array;

    // =========================================================================
    // SERVICE REGISTRATION
    // =========================================================================

    /**
     * Smart registration with hash-based idempotency
     *
     * Only registers with gNode if the configuration has changed.
     * Prevents unnecessary re-registration while detecting config changes.
     *
     * @param array $capabilities Capability vector for gNode topology
     * @param array $metadata Service metadata (type, site_id, etc.)
     * @param bool $force Force registration even if hash matches
     * @return bool Success status
     */
    public function smartRegister(array $capabilities = [], array $metadata = [], bool $force = false): bool;

    /**
     * Force re-registration (clears hash and registers)
     *
     * @param array $capabilities Optional capability override
     * @param array $metadata Optional metadata override
     * @return bool Success status
     */
    public function forceRegister(array $capabilities = [], array $metadata = []): bool;

    /**
     * Deregister current service from gNode topology
     *
     * @return bool Success status
     */
    public function deregister(): bool;

    /**
     * Update capabilities dynamically (without full re-registration)
     *
     * @param array $capabilities New or updated capabilities
     * @param array $metadata Optional metadata updates
     * @return bool Success status
     */
    public function updateCapabilities(array $capabilities, array $metadata = []): bool;

    /**
     * Get registration status
     *
     * @return array Registration status (registered, hash, service_id, etc.)
     */
    public function getRegistrationStatus(): array;

    /**
     * Reload configuration and re-register if changed
     *
     * @param callable|null $configLoader Optional callback to load fresh config
     * @return array Result with 'changed' and 'registered' status
     */
    public function refreshRegistration(?callable $configLoader = null): array;

    /**
     * Check if service is currently registered in topology
     *
     * @return bool True if service exists in gNode topology
     */
    public function isRegisteredInTopology(): bool;

    // =========================================================================
    // CAPABILITY DISCOVERY
    // =========================================================================

    /**
     * Get capability vector for GeometricTopology service discovery
     *
     * @return array Capability vector with dimension scores (0.0-1.0)
     */
    public function getCapabilityVector(): array;
}
