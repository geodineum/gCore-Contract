<?php
/**
 * MetricsManager Interface
 *
 * Contract for metrics reading and observability functionality.
 * Premium implementations provide full ValKey-backed metrics.
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
 * Interface MetricsManagerInterface
 *
 * Defines the contract for metrics collection and retrieval.
 */
interface MetricsManagerInterface extends ModuleInterface
{
    /**
     * Get all metrics counters for the site
     *
     * @return array Associative array of metric_name => count
     */
    public function getAllMetrics(): array;

    /**
     * Get a specific metric counter
     *
     * @param string $metricType Metric type (e.g., 'cache_hits', 'locks_acquired')
     * @return int Metric count
     */
    public function getMetric(string $metricType): int;

    /**
     * Get detailed entries for a specific metric type
     *
     * @param string $metricType Metric type
     * @param int|null $limit Max entries to return (null for default)
     * @return array List of detailed metric entries
     */
    public function getMetricDetails(string $metricType, ?int $limit = null): array;

    /**
     * Get latency statistics
     *
     * @param int|null $windowSeconds Time window in seconds (null for default)
     * @return array Latency stats with min, max, avg, p50, p95, p99, count
     */
    public function getLatencyStats(?int $windowSeconds = null): array;

    /**
     * Get global metrics (cross-site aggregated)
     *
     * @return array Global metrics counters
     */
    public function getGlobalMetrics(): array;

    /**
     * Get metrics summary with categorized stats
     *
     * @return array Categorized metrics summary
     */
    public function getSummary(): array;

    /**
     * Get cache hit ratio
     *
     * @return float|null Hit ratio (0.0-1.0) or null if no data available
     */
    public function getCacheHitRatio(): ?float;

    /**
     * Get operations per second estimate
     *
     * @param string $metricType Metric to measure
     * @param int $windowSeconds Time window (default 60)
     * @return float Operations per second
     */
    public function getOpsPerSecond(string $metricType, int $windowSeconds = 60): float;

    /**
     * Track a metric by incrementing its counter
     *
     * @param string $metricType Metric type name
     * @param int $value Value to increment by (default 1)
     * @param array|null $extra Optional extra data to store
     * @return bool Success
     */
    public function trackMetric(string $metricType, int $value = 1, ?array $extra = null): bool;

    /**
     * Record a metric (alias for trackMetric)
     *
     * @param string $metricType Metric type name
     * @param array $data Metric data
     * @return bool Success
     */
    public function recordMetric(string $metricType, array $data = []): bool;
}
