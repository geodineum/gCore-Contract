<?php
/**
 * Module Interface
 *
 * Base interface for all gCore managers. Defines the lifecycle methods
 * that every module must implement for proper initialization, shutdown,
 * and status reporting.
 *
 * @package     gCore\Contracts
 * @subpackage  Shared
 * @version     1.0.0
 * @since       3.0.0
 */

namespace gCore\Contracts\Shared;

/**
 * Interface ModuleInterface
 *
 * Core contract for all gCore modules/managers.
 */
interface ModuleInterface
{
    /**
     * Get singleton instance of the module
     *
     * @return ModuleInterface
     */
    public static function getInstance(): ModuleInterface;

    /**
     * Initialize the module with configuration
     *
     * @param array $config Configuration options
     * @return void
     */
    public function initialize(array $config = []): void;

    /**
     * Shutdown the module and cleanup resources
     *
     * @return void
     */
    public function shutdown(): void;

    /**
     * Check if module is initialized
     *
     * @return bool
     */
    public function isInitialized(): bool;

    /**
     * Get module status information
     *
     * Should include at minimum:
     * - 'initialized' => bool
     * - 'stub_mode' => bool (true for free-tier stubs)
     *
     * @return array Status information
     */
    public function getStatus(): array;

    /**
     * Get current configuration
     *
     * @return array Configuration
     */
    public function getConfig(): array;

    /**
     * Update configuration (merge with existing)
     *
     * @param array $config Configuration to merge
     * @return void
     */
    public function updateConfig(array $config): void;

    /**
     * Get capability vector for GeometricTopology service discovery
     *
     * Returns an array of dimension => value pairs representing
     * the module's capabilities. Values typically range from 0.0 to 1.0.
     *
     * Example:
     * [
     *     'caching' => 0.9,
     *     'persistence' => 1.0,
     *     'real_time' => 0.5,
     * ]
     *
     * @return array Capability vector
     */
    public function getCapabilityVector(): array;
}
