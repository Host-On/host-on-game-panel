<?php

namespace Pterodactyl\Services\Infrastructure;

use Pterodactyl\Models\InfrastructureHost;
use Illuminate\Support\Collection;

/**
 * Scores eligible compute hosts so that provisioning selects the most suitable
 * node instead of simply picking the first one.
 *
 * Physical capacity (CPU/RAM/disk/status) is synchronized from Proxmox;
 * Host-On settings (placement weight, reserved capacity, allowed product
 * classes, maintenance) are applied on top.
 */
class PlacementEngine
{
    public const SCORE_WEIGHTS = [
        'memory' => 35,
        'cpu' => 30,
        'storage' => 25,
        'load' => 10,
    ];

    /**
     * Return hosts that satisfy the requirements, sorted best-first.
     *
     * @param  Collection<int, InfrastructureHost>  $hosts
     * @return array<int, array{host: InfrastructureHost, score: float, reasons: string[], excluded: bool}>
     */
    public function rank(Collection $hosts, int $cpu, int $memory, int $disk, ?string $productClass = null): array
    {
        $results = [];

        foreach ($hosts as $host) {
            $results[] = $this->score($host, $cpu, $memory, $disk, $productClass);
        }

        // Sort: non-excluded first, then by score descending.
        usort($results, function ($a, $b) {
            if ($a['excluded'] !== $b['excluded']) {
                return $a['excluded'] ? 1 : -1;
            }

            return $b['score'] <=> $a['score'];
        });

        return $results;
    }

    /**
     * Score a single host against the given requirements.
     *
     * @return array{host: InfrastructureHost, score: float, reasons: string[], excluded: bool}
     */
    public function score(InfrastructureHost $host, int $cpu, int $memory, int $disk, ?string $productClass = null): array
    {
        $reasons = [];

        if (!$host->enabled) {
            return ['host' => $host, 'score' => 0.0, 'reasons' => ['Host is disabled.'], 'excluded' => true];
        }

        if ($host->maintenance_mode) {
            return ['host' => $host, 'score' => 0.0, 'reasons' => ['Host is in maintenance mode.'], 'excluded' => true];
        }

        if ($host->status !== null && $host->status !== 'online') {
            return ['host' => $host, 'score' => 0.0, 'reasons' => [sprintf('Host is offline (status: %s).', $host->status)], 'excluded' => true];
        }

        if ($productClass !== null && !empty($host->allowed_product_classes) && !in_array($productClass, $host->allowed_product_classes, true)) {
            return ['host' => $host, 'score' => 0.0, 'reasons' => [sprintf('Product class "%s" is not allowed on this host.', $productClass)], 'excluded' => true];
        }

        // Free capacity accounts for both allocated and reserved resources.
        $freeMemory = max(0, $host->max_memory - $host->allocated_memory - $host->reserved_memory);
        $freeDisk = max(0, $host->max_disk - $host->allocated_disk - $host->reserved_disk);
        $freeCpu = max(0, $host->cpu_cores);

        if ($freeMemory < $memory) {
            return ['host' => $host, 'score' => 0.0, 'reasons' => [sprintf('Insufficient RAM (%d MiB free, %d required).', $freeMemory, $memory)], 'excluded' => true];
        }

        if ($freeDisk < $disk) {
            return ['host' => $host, 'score' => 0.0, 'reasons' => [sprintf('Insufficient storage (%d GiB free, %d required).', $freeDisk, $disk)], 'excluded' => true];
        }

        if ($cpu > $freeCpu) {
            return ['host' => $host, 'score' => 0.0, 'reasons' => [sprintf('Insufficient CPU (%d cores, %d required).', $freeCpu, $cpu)], 'excluded' => true];
        }

        // Memory: how much headroom remains after placement (0-1).
        $memoryScore = $this->clamp($freeMemory > 0 ? 1 - ($memory / $freeMemory) : 0);
        // CPU: inverse of current utilization (0-1).
        $cpuScore = $this->clamp(1 - ($host->cpu_utilization / 100));
        // Storage headroom (0-1).
        $storageScore = $this->clamp($freeDisk > 0 ? 1 - ($disk / $freeDisk) : 0);
        // Load: combine CPU utilization and memory utilization.
        $load = ($host->cpu_utilization + $host->memory_utilization) / 2;
        $loadScore = $this->clamp(1 - ($load / 100));

        $score = ($memoryScore * self::SCORE_WEIGHTS['memory'])
            + ($cpuScore * self::SCORE_WEIGHTS['cpu'])
            + ($storageScore * self::SCORE_WEIGHTS['storage'])
            + ($loadScore * self::SCORE_WEIGHTS['load']);

        // Apply the Host-On placement weight (default 100 = neutral).
        $weight = max(0, $host->placement_weight ?: 100);
        $score = $score * ($weight / 100);

        $reasons[] = sprintf('RAM free: %d%%', (int) round($memoryScore * 100));
        $reasons[] = sprintf('CPU load: %d%%', (int) $host->cpu_utilization);
        $reasons[] = sprintf('Storage free: %d%%', (int) round($storageScore * 100));

        return ['host' => $host, 'score' => round($score, 2), 'reasons' => $reasons, 'excluded' => false];
    }

    protected function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
