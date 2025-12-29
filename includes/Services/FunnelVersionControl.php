<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for funnel version control - backup, restore, and diff operations.
 */
class FunnelVersionControl
{
    /**
     * Meta key for storing versions.
     */
    private const META_VERSIONS = '_hp_funnel_versions';

    /**
     * Meta key for current version pointer.
     */
    private const META_CURRENT_VERSION = '_hp_funnel_current_version';

    /**
     * Meta key for AI activity log.
     */
    private const META_AI_ACTIVITY = '_hp_funnel_ai_activity';

    /**
     * Option name for version control settings.
     */
    private const OPTION_SETTINGS = 'hp_funnel_version_control';

    /**
     * Default settings.
     */
    private const DEFAULT_SETTINGS = [
        'max_versions' => 20,
        'auto_backup_on_update' => true,
        'retention_days' => 90,
    ];

    /**
     * Create a backup of current funnel state.
     *
     * @param int $postId Funnel post ID
     * @param string $description Version description
     * @param string $createdBy Who created the version ('admin', 'ai_agent', 'import')
     * @return string Version ID
     */
    public static function createVersion(int $postId, string $description = '', string $createdBy = 'admin'): string
    {
        // Get current funnel data
        $funnelData = FunnelExporter::exportFunnel($postId);
        
        if (!$funnelData) {
            return '';
        }

        // Generate version ID
        $versionId = 'v_' . time();

        // Get existing versions
        $versions = self::getVersionsRaw($postId);

        // Create new version entry
        $newVersion = [
            'version_id' => $versionId,
            'created_at' => gmdate('c'),
            'created_by' => $createdBy,
            'description' => $description ?: 'Manual backup',
            'snapshot' => $funnelData,
            'changes_summary' => null,
        ];

        // Add to beginning of array (newest first)
        array_unshift($versions, $newVersion);

        // Prune old versions
        $versions = self::pruneVersionsArray($versions);

        // Save versions
        update_post_meta($postId, self::META_VERSIONS, $versions);
        update_post_meta($postId, self::META_CURRENT_VERSION, $versionId);

        // Log AI activity if created by AI
        if ($createdBy === 'ai_agent') {
            self::logAiActivity($postId, 'backup_created', $description);
        }

        return $versionId;
    }

    /**
     * Restore funnel to a specific version.
     *
     * @param int $postId Funnel post ID
     * @param string $versionId Version ID to restore
     * @param bool $backupCurrent Whether to backup current state before restoring
     * @return array Result with success status
     */
    public static function restoreVersion(int $postId, string $versionId, bool $backupCurrent = true): array
    {
        // Get the version to restore
        $versions = self::getVersionsRaw($postId);
        $targetVersion = null;

        foreach ($versions as $version) {
            if ($version['version_id'] === $versionId) {
                $targetVersion = $version;
                break;
            }
        }

        if (!$targetVersion) {
            return [
                'success' => false,
                'error' => 'Version not found',
            ];
        }

        // Backup current state if requested
        $backupVersionId = null;
        if ($backupCurrent) {
            $backupVersionId = self::createVersion($postId, "Before rollback to {$versionId}", 'admin');
        }

        // Import the snapshot
        $snapshot = $targetVersion['snapshot'];
        
        if (!$snapshot) {
            return [
                'success' => false,
                'error' => 'Version snapshot is empty',
            ];
        }

        // Use FunnelImporter to restore
        $importResult = FunnelImporter::importFunnel($snapshot, 'update', $postId);

        if (!$importResult['success']) {
            return [
                'success' => false,
                'error' => 'Failed to restore version: ' . ($importResult['message'] ?? 'Unknown error'),
            ];
        }

        // Update current version pointer
        update_post_meta($postId, self::META_CURRENT_VERSION, $versionId);

        return [
            'success' => true,
            'restored_version' => $versionId,
            'backup_created' => $backupVersionId,
            'message' => "Restored to version from " . $targetVersion['created_at'],
        ];
    }

    /**
     * Get all versions for a funnel (formatted for API).
     *
     * @param int $postId Funnel post ID
     * @return array Versions list
     */
    public static function getVersions(int $postId): array
    {
        $versions = self::getVersionsRaw($postId);
        $currentVersion = get_post_meta($postId, self::META_CURRENT_VERSION, true);

        $formatted = [];
        foreach ($versions as $version) {
            $formatted[] = [
                'version_id' => $version['version_id'],
                'created_at' => $version['created_at'],
                'created_by' => $version['created_by'],
                'description' => $version['description'],
                'is_current' => $version['version_id'] === $currentVersion,
                'changes_summary' => $version['changes_summary'],
            ];
        }

        return [
            'funnel_id' => $postId,
            'current_version' => $currentVersion,
            'versions_count' => count($formatted),
            'versions' => $formatted,
        ];
    }

    /**
     * Get a specific version's snapshot.
     *
     * @param int $postId Funnel post ID
     * @param string $versionId Version ID
     * @return array|null Version snapshot or null
     */
    public static function getVersionSnapshot(int $postId, string $versionId): ?array
    {
        $versions = self::getVersionsRaw($postId);

        foreach ($versions as $version) {
            if ($version['version_id'] === $versionId) {
                return $version['snapshot'];
            }
        }

        return null;
    }

    /**
     * Compare two versions and return diff.
     *
     * @param int $postId Funnel post ID
     * @param string $fromId From version ID
     * @param string $toId To version ID
     * @return array Diff result
     */
    public static function diffVersions(int $postId, string $fromId, string $toId): array
    {
        $fromSnapshot = self::getVersionSnapshot($postId, $fromId);
        $toSnapshot = self::getVersionSnapshot($postId, $toId);

        if (!$fromSnapshot || !$toSnapshot) {
            return [
                'error' => 'One or both versions not found',
            ];
        }

        $changes = self::calculateDiff($fromSnapshot, $toSnapshot);
        $sectionsChanged = array_keys($changes);

        return [
            'from_version' => $fromId,
            'to_version' => $toId,
            'changes' => $changes,
            'sections_changed' => $sectionsChanged,
            'summary' => self::generateDiffSummary($changes),
        ];
    }

    /**
     * Log AI activity on a funnel.
     *
     * @param int $postId Funnel post ID
     * @param string $action Action performed
     * @param string $description Description of the action
     * @param array $details Additional details
     */
    public static function logAiActivity(int $postId, string $action, string $description = '', array $details = []): void
    {
        $activities = get_post_meta($postId, self::META_AI_ACTIVITY, true) ?: [];

        $entry = [
            'timestamp' => gmdate('c'),
            'action' => $action,
            'description' => $description,
            'details' => $details,
        ];

        array_unshift($activities, $entry);

        // Keep only last 50 activities
        $activities = array_slice($activities, 0, 50);

        update_post_meta($postId, self::META_AI_ACTIVITY, $activities);
    }

    /**
     * Get AI activity log for a funnel.
     *
     * @param int $postId Funnel post ID
     * @param int $limit Max entries to return
     * @return array Activity log
     */
    public static function getAiActivity(int $postId, int $limit = 10): array
    {
        $activities = get_post_meta($postId, self::META_AI_ACTIVITY, true) ?: [];
        
        return array_slice($activities, 0, $limit);
    }

    /**
     * Get all AI activities across all funnels.
     *
     * @param int $limit Max entries per funnel
     * @return array Activities grouped by funnel
     */
    public static function getAllAiActivity(int $limit = 50): array
    {
        $funnels = get_posts([
            'post_type' => 'hp-funnel',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $allActivities = [];

        foreach ($funnels as $postId) {
            $activities = get_post_meta($postId, self::META_AI_ACTIVITY, true) ?: [];
            $funnelSlug = get_field('funnel_slug', $postId) ?: get_post_field('post_name', $postId);
            $funnelName = get_the_title($postId);

            foreach ($activities as $activity) {
                $allActivities[] = array_merge($activity, [
                    'funnel_id' => $postId,
                    'funnel_slug' => $funnelSlug,
                    'funnel_name' => $funnelName,
                ]);
            }
        }

        // Sort by timestamp descending
        usort($allActivities, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return array_slice($allActivities, 0, $limit);
    }

    /**
     * Get version control settings.
     *
     * @return array Settings
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_SETTINGS, []);
        return array_merge(self::DEFAULT_SETTINGS, $stored);
    }

    /**
     * Save version control settings.
     *
     * @param array $settings Settings to save
     * @return bool Success
     */
    public static function saveSettings(array $settings): bool
    {
        return update_option(self::OPTION_SETTINGS, $settings);
    }

    /**
     * Cleanup old versions based on retention policy.
     *
     * @param int $postId Funnel post ID
     * @return int Number of versions pruned
     */
    public static function pruneVersions(int $postId): int
    {
        $versions = self::getVersionsRaw($postId);
        $originalCount = count($versions);
        
        $pruned = self::pruneVersionsArray($versions);
        $prunedCount = $originalCount - count($pruned);

        if ($prunedCount > 0) {
            update_post_meta($postId, self::META_VERSIONS, $pruned);
        }

        return $prunedCount;
    }

    /**
     * Get raw versions array from meta.
     *
     * @param int $postId Funnel post ID
     * @return array Versions
     */
    private static function getVersionsRaw(int $postId): array
    {
        $versions = get_post_meta($postId, self::META_VERSIONS, true);
        return is_array($versions) ? $versions : [];
    }

    /**
     * Prune versions array based on settings.
     *
     * @param array $versions Versions array
     * @return array Pruned versions
     */
    private static function pruneVersionsArray(array $versions): array
    {
        $settings = self::getSettings();
        $maxVersions = $settings['max_versions'];
        $retentionDays = $settings['retention_days'];
        $cutoffDate = gmdate('c', strtotime("-{$retentionDays} days"));

        $pruned = [];
        $count = 0;

        foreach ($versions as $version) {
            // Keep if within max count
            if ($count >= $maxVersions) {
                break;
            }

            // Keep if within retention period OR if it's one of the first 3 (always keep recent)
            if ($count < 3 || $version['created_at'] >= $cutoffDate) {
                $pruned[] = $version;
                $count++;
            }
        }

        return $pruned;
    }

    /**
     * Calculate diff between two snapshots.
     *
     * @param array $from From snapshot
     * @param array $to To snapshot
     * @return array Changes by section
     */
    private static function calculateDiff(array $from, array $to): array
    {
        $changes = [];
        $sections = ['funnel', 'header', 'hero', 'benefits', 'offers', 'features', 'authority', 'science', 'testimonials', 'faq', 'cta', 'checkout', 'thankyou', 'styling', 'footer'];

        foreach ($sections as $section) {
            $fromSection = $from[$section] ?? [];
            $toSection = $to[$section] ?? [];

            $sectionChanges = self::diffArrays($fromSection, $toSection);
            
            if (!empty($sectionChanges)) {
                $changes[$section] = $sectionChanges;
            }
        }

        return $changes;
    }

    /**
     * Diff two arrays recursively.
     *
     * @param mixed $from From value
     * @param mixed $to To value
     * @param string $path Current path
     * @return array Changes
     */
    private static function diffArrays($from, $to, string $path = ''): array
    {
        $changes = [];

        // Handle type differences
        if (gettype($from) !== gettype($to)) {
            return ['from' => $from, 'to' => $to];
        }

        // Handle non-arrays
        if (!is_array($from) || !is_array($to)) {
            if ($from !== $to) {
                return ['from' => $from, 'to' => $to];
            }
            return [];
        }

        // Handle arrays
        $allKeys = array_unique(array_merge(array_keys($from), array_keys($to)));

        foreach ($allKeys as $key) {
            $fromValue = $from[$key] ?? null;
            $toValue = $to[$key] ?? null;

            if ($fromValue !== $toValue) {
                if (is_array($fromValue) && is_array($toValue)) {
                    $nestedChanges = self::diffArrays($fromValue, $toValue);
                    if (!empty($nestedChanges)) {
                        $changes[$key] = $nestedChanges;
                    }
                } else {
                    $changes[$key] = [
                        'from' => $fromValue,
                        'to' => $toValue,
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Generate a human-readable summary of changes.
     *
     * @param array $changes Changes array
     * @return string Summary
     */
    private static function generateDiffSummary(array $changes): string
    {
        $parts = [];

        foreach ($changes as $section => $sectionChanges) {
            $fieldCount = self::countChangedFields($sectionChanges);
            $parts[] = ucfirst($section) . " ({$fieldCount} field" . ($fieldCount !== 1 ? 's' : '') . ")";
        }

        return implode(', ', $parts);
    }

    /**
     * Count changed fields in a changes array.
     *
     * @param array $changes Changes array
     * @return int Count
     */
    private static function countChangedFields(array $changes): int
    {
        $count = 0;

        foreach ($changes as $value) {
            if (is_array($value) && isset($value['from']) && array_key_exists('to', $value)) {
                $count++;
            } elseif (is_array($value)) {
                $count += self::countChangedFields($value);
            }
        }

        return max($count, 1);
    }
}















