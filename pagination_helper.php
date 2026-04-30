<?php
/**
 * Simple pagination helper.
 * Usage:
 *   $pagination = paginate_setup($total_rows, 10, 'page');
 *   // Append LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']} to your query
 *   echo render_pagination($pagination, 'page'); // preserves other GET params
 */

if (!function_exists('paginate_setup')) {
    function paginate_setup($total_rows, $per_page = 10, $param = 'page') {
        $per_page = max(1, intval($per_page));
        $total_rows = max(0, intval($total_rows));
        $total_pages = max(1, (int)ceil($total_rows / $per_page));
        $current = isset($_GET[$param]) ? max(1, intval($_GET[$param])) : 1;
        if ($current > $total_pages) $current = $total_pages;
        $offset = ($current - 1) * $per_page;
        return [
            'per_page' => $per_page,
            'total_rows' => $total_rows,
            'total_pages' => $total_pages,
            'current' => $current,
            'offset' => $offset,
            'param' => $param,
        ];
    }
}

if (!function_exists('render_pagination')) {
    function render_pagination($p, $param = null) {
        $param = $param ?? ($p['param'] ?? 'page');
        $total_pages = $p['total_pages'];
        $current = $p['current'];
        if ($total_pages <= 1) return '';

        // Preserve existing query params except the page param
        $query = $_GET;
        unset($query[$param]);
        $base = $_SERVER['PHP_SELF'];
        $build = function($pageNum) use ($base, $query, $param) {
            $q = $query;
            $q[$param] = $pageNum;
            return htmlspecialchars($base . '?' . http_build_query($q));
        };

        // Window: show up to 5 numeric pages centred around current
        $window = 5;
        $start = max(1, $current - (int)floor($window / 2));
        $end = min($total_pages, $start + $window - 1);
        if ($end - $start + 1 < $window) {
            $start = max(1, $end - $window + 1);
        }

        $html = '<nav class="pagination-nav" style="margin:15px 0; text-align:center;">';
        $html .= '<ul class="pagination" style="display:inline-flex; list-style:none; padding:0; gap:4px; margin:0;">';

        $btnStyle = 'display:inline-block; padding:6px 12px; border:1px solid #ccc; border-radius:4px; color:#650000; text-decoration:none; background:#fff;';
        $activeStyle = 'display:inline-block; padding:6px 12px; border:1px solid #650000; border-radius:4px; color:#fff; background:#650000; font-weight:bold;';
        $disabledStyle = 'display:inline-block; padding:6px 12px; border:1px solid #eee; border-radius:4px; color:#aaa; background:#f8f8f8; cursor:not-allowed;';

        // Prev
        if ($current > 1) {
            $html .= '<li><a href="' . $build($current - 1) . '" style="' . $btnStyle . '">&laquo; Prev</a></li>';
        } else {
            $html .= '<li><span style="' . $disabledStyle . '">&laquo; Prev</span></li>';
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $current) {
                $html .= '<li><span style="' . $activeStyle . '">' . $i . '</span></li>';
            } else {
                $html .= '<li><a href="' . $build($i) . '" style="' . $btnStyle . '">' . $i . '</a></li>';
            }
        }

        // Next
        if ($current < $total_pages) {
            $html .= '<li><a href="' . $build($current + 1) . '" style="' . $btnStyle . '">Next &raquo;</a></li>';
        } else {
            $html .= '<li><span style="' . $disabledStyle . '">Next &raquo;</span></li>';
        }

        $html .= '</ul>';
        $html .= '<div style="margin-top:6px; font-size:12px; color:#666;">Page ' . $current . ' of ' . $total_pages . ' &bull; ' . $p['total_rows'] . ' total</div>';
        $html .= '</nav>';

        return $html;
    }
}
