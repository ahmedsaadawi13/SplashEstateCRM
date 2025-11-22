<?php
// FILE: /app/core/View.php

/**
 * SplashEstate CRM - View Class
 * Helper methods for views
 */

class View {

    /**
     * Escape HTML output
     * @param string $string String to escape
     * @return string Escaped string
     */
    public static function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format date for display
     * @param string $date Date string
     * @param string $format Output format
     * @return string Formatted date
     */
    public static function formatDate($date, $format = 'Y-m-d H:i') {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        return date($format, strtotime($date));
    }

    /**
     * Format currency
     * @param float $amount Amount
     * @param string $currency Currency symbol
     * @return string Formatted currency
     */
    public static function formatCurrency($amount, $currency = '$') {
        return $currency . number_format($amount, 2);
    }

    /**
     * Generate pagination HTML
     * @param int $currentPage Current page
     * @param int $totalPages Total pages
     * @param string $baseUrl Base URL for pagination links
     * @return string Pagination HTML
     */
    public static function pagination($currentPage, $totalPages, $baseUrl) {
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<div class="pagination">';

        // Previous button
        if ($currentPage > 1) {
            $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage - 1) . '" class="page-link">&laquo; Previous</a>';
        }

        // Page numbers
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i === $currentPage) ? ' active' : '';
            $html .= '<a href="' . $baseUrl . '?page=' . $i . '" class="page-link' . $active . '">' . $i . '</a>';
        }

        // Next button
        if ($currentPage < $totalPages) {
            $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage + 1) . '" class="page-link">Next &raquo;</a>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Truncate text
     * @param string $text Text to truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix to append
     * @return string Truncated text
     */
    public static function truncate($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . $suffix;
    }

    /**
     * Get status badge HTML
     * @param string $status Status value
     * @return string HTML badge
     */
    public static function statusBadge($status) {
        $badges = array(
            'new' => 'badge-primary',
            'contacted' => 'badge-info',
            'qualified' => 'badge-success',
            'proposal' => 'badge-warning',
            'negotiation' => 'badge-warning',
            'won' => 'badge-success',
            'lost' => 'badge-danger',
            'active' => 'badge-success',
            'inactive' => 'badge-secondary',
            'pending' => 'badge-warning',
            'completed' => 'badge-success',
            'cancelled' => 'badge-danger'
        );

        $class = isset($badges[$status]) ? $badges[$status] : 'badge-secondary';
        return '<span class="badge ' . $class . '">' . ucfirst($status) . '</span>';
    }
}
