<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Points service for YITH Points and Rewards integration.
 * 
 * Provides methods for checking customer points balance and converting
 * points to monetary value.
 */
class PointsService
{
    /**
     * Get the current points balance for a customer.
     * 
     * @param int $userId WordPress user ID
     * @return int Points balance
     */
    public function getCustomerPoints(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        // Try EAO helper first
        if (function_exists('eao_yith_get_customer_points')) {
            return (int) \eao_yith_get_customer_points($userId);
        }

        // Fallback to YITH meta directly
        $raw = get_user_meta($userId, '_ywpar_user_total_points', true);
        return is_numeric($raw) ? (int) $raw : 0;
    }

    /**
     * Convert points to monetary value.
     * 
     * @param int $points Number of points
     * @return float Monetary value
     */
    public function pointsToMoney(int $points): float
    {
        // Default is 10 points per $1.00
        $pointsPerDollar = (int) apply_filters('hp_rw_points_dollar_rate', 10);
        if ($pointsPerDollar <= 0) {
            $pointsPerDollar = 10;
        }

        return round($points / $pointsPerDollar, 2);
    }

    /**
     * Convert monetary value to points.
     * 
     * @param float $money Monetary value
     * @return int Number of points
     */
    public function moneyToPoints(float $money): int
    {
        $pointsPerDollar = (int) apply_filters('hp_rw_points_dollar_rate', 10);
        if ($pointsPerDollar <= 0) {
            $pointsPerDollar = 10;
        }

        return (int) round($money * $pointsPerDollar);
    }

    /**
     * Deduct points from a customer's balance.
     * 
     * @param int $userId WordPress user ID
     * @param int $points Number of points to deduct
     * @param string $reason Reason for deduction
     * @return bool Success status
     */
    public function deductPoints(int $userId, int $points, string $reason = ''): bool
    {
        if ($userId <= 0 || $points <= 0) {
            return false;
        }

        // Try YITH API if available
        if (function_exists('YITH_WC_Points_Rewards')) {
            try {
                $yith = YITH_WC_Points_Rewards();
                if (method_exists($yith, 'get_points_manager')) {
                    $manager = $yith->get_points_manager();
                    if ($manager && method_exists($manager, 'remove_points')) {
                        $manager->remove_points($userId, $points, $reason ?: 'Points redeemed via HP React Widgets');
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to manual deduction
            }
        }

        // Manual fallback - update the meta directly
        $current = $this->getCustomerPoints($userId);
        $newBalance = max(0, $current - $points);
        update_user_meta($userId, '_ywpar_user_total_points', $newBalance);

        return true;
    }

    /**
     * Add points to a customer's balance.
     * 
     * @param int $userId WordPress user ID
     * @param int $points Number of points to add
     * @param string $reason Reason for addition
     * @return bool Success status
     */
    public function addPoints(int $userId, int $points, string $reason = ''): bool
    {
        if ($userId <= 0 || $points <= 0) {
            return false;
        }

        // Try YITH API if available
        if (function_exists('YITH_WC_Points_Rewards')) {
            try {
                $yith = YITH_WC_Points_Rewards();
                if (method_exists($yith, 'get_points_manager')) {
                    $manager = $yith->get_points_manager();
                    if ($manager && method_exists($manager, 'add_points')) {
                        $manager->add_points($userId, $points, $reason ?: 'Points added via HP React Widgets');
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to manual addition
            }
        }

        // Manual fallback
        $current = $this->getCustomerPoints($userId);
        $newBalance = $current + $points;
        update_user_meta($userId, '_ywpar_user_total_points', $newBalance);

        return true;
    }
}















