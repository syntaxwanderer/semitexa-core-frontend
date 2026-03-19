<?php

declare(strict_types=1);

namespace Semitexa\Ssr\Asset;

use Semitexa\Ssr\Asset\Exception\CircularDependencyException;

/**
 * Resolves asset dependency order via topological sort (Kahn's algorithm).
 *
 * Secondary sort key is priority (lower = earlier) within the same dependency level.
 */
final class AssetResolver
{
    /**
     * Return assets in dependency-resolved order.
     *
     * @param array<string, AssetEntry> $entries Keyed by canonical asset key
     * @return AssetEntry[]
     *
     * @throws CircularDependencyException when the dependency graph contains a cycle
     */
    public static function topologicalSort(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        // Build adjacency list and in-degree map (only for known entries)
        /** @var array<string, string[]> $dependents key → list of keys that depend on it */
        $dependents = [];
        /** @var array<string, int> $inDegree */
        $inDegree = [];

        foreach ($entries as $key => $entry) {
            $inDegree[$key] ??= 0;
            $dependents[$key] ??= [];

            foreach ($entry->dependencies as $dep) {
                if (!isset($entries[$dep])) {
                    // Dependency not in the required set — skip (already loaded or unknown)
                    continue;
                }
                $dependents[$dep][] = $key;
                $inDegree[$key] = ($inDegree[$key] ?? 0) + 1;
            }
        }

        // Collect nodes with zero in-degree, sorted by priority
        $queue = [];
        foreach ($inDegree as $key => $degree) {
            if ($degree === 0) {
                $queue[] = $key;
            }
        }
        usort($queue, static fn (string $a, string $b) => $entries[$a]->priority <=> $entries[$b]->priority);

        $sorted = [];
        $processed = 0;

        while ($queue !== []) {
            $current = array_shift($queue);
            $sorted[] = $entries[$current];
            $processed++;

            $newReady = [];
            foreach ($dependents[$current] ?? [] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $newReady[] = $dependent;
                }
            }

            // Sort newly ready nodes by priority before adding to queue
            if ($newReady !== []) {
                usort($newReady, static fn (string $a, string $b) => $entries[$a]->priority <=> $entries[$b]->priority);
                // Insert in priority order
                foreach ($newReady as $key) {
                    // Find correct insertion position
                    $inserted = false;
                    for ($i = 0, $len = count($queue); $i < $len; $i++) {
                        if ($entries[$key]->priority < $entries[$queue[$i]]->priority) {
                            array_splice($queue, $i, 0, [$key]);
                            $inserted = true;
                            break;
                        }
                    }
                    if (!$inserted) {
                        $queue[] = $key;
                    }
                }
            }
        }

        if ($processed < count($entries)) {
            // Detect the cycle path for a meaningful error message
            $remaining = array_diff(array_keys($entries), array_map(
                static fn (AssetEntry $e) => $e->key,
                $sorted,
            ));
            throw new CircularDependencyException(array_values($remaining));
        }

        return $sorted;
    }
}
