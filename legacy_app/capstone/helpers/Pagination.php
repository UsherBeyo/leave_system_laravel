<?php
if (!function_exists('paginate_array')) {
    function paginate_array(array $items, int $page, int $perPage = 10): array
    {
        $total = count($items);
        $perPage = max(1, $perPage);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'offset' => $offset,
            'from' => $total ? ($offset + 1) : 0,
            'to' => $total ? min($offset + $perPage, $total) : 0,
        ];
    }
}

if (!function_exists('pagination_render')) {
    function pagination_render(array $pagination, string $pageParam = 'page', array $extraParams = []): string
    {
        if (($pagination['total_pages'] ?? 1) <= 1) {
            return '';
        }

        $current = (int)($pagination['page'] ?? 1);
        $totalPages = (int)($pagination['total_pages'] ?? 1);
        $windowStart = max(1, $current - 2);
        $windowEnd = min($totalPages, $current + 2);

        $buildUrl = function(int $target) use ($pageParam, $extraParams): string {
            $params = array_merge($_GET, $extraParams, [$pageParam => $target]);
            foreach ($params as $k => $v) {
                if ($v === null || $v === '') unset($params[$k]);
            }

            $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
            $path = $requestUri !== '' ? (parse_url($requestUri, PHP_URL_PATH) ?: '') : '';
            if ($path === '') {
                $path = (string)($_SERVER['PHP_SELF'] ?? '');
            }
            if ($path === '') {
                $path = './';
            }

            $query = http_build_query($params);
            return $query !== '' ? ($path . '?' . $query) : $path;
        };

        $html = '<nav class="pagination-bar" aria-label="Pagination">';
        $html .= '<div class="pagination-summary">Showing ' . (int)$pagination['from'] . '–' . (int)$pagination['to'] . ' of ' . (int)$pagination['total'] . '</div>';
        $html .= '<div class="pagination-links">';

        if ($current > 1) {
            $html .= '<a class="pagination-link" href="' . htmlspecialchars($buildUrl($current - 1), ENT_QUOTES, 'UTF-8') . '">Previous</a>';
        } else {
            $html .= '<span class="pagination-link is-disabled">Previous</span>';
        }

        if ($windowStart > 1) {
            $html .= '<a class="pagination-link" href="' . htmlspecialchars($buildUrl(1), ENT_QUOTES, 'UTF-8') . '">1</a>';
            if ($windowStart > 2) {
                $html .= '<span class="pagination-ellipsis">…</span>';
            }
        }

        for ($i = $windowStart; $i <= $windowEnd; $i++) {
            if ($i === $current) {
                $html .= '<span class="pagination-link is-active">' . $i . '</span>';
            } else {
                $html .= '<a class="pagination-link" href="' . htmlspecialchars($buildUrl($i), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
            }
        }

        if ($windowEnd < $totalPages) {
            if ($windowEnd < $totalPages - 1) {
                $html .= '<span class="pagination-ellipsis">…</span>';
            }
            $html .= '<a class="pagination-link" href="' . htmlspecialchars($buildUrl($totalPages), ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a>';
        }

        if ($current < $totalPages) {
            $html .= '<a class="pagination-link" href="' . htmlspecialchars($buildUrl($current + 1), ENT_QUOTES, 'UTF-8') . '">Next</a>';
        } else {
            $html .= '<span class="pagination-link is-disabled">Next</span>';
        }

        $html .= '</div></nav>';
        return $html;
    }
}


if (!function_exists('pagination_filter_array')) {
    function pagination_filter_array(array $items, string $term, array $fields): array
    {
        $term = trim((string)$term);
        if ($term === '') {
            return $items;
        }

        $needle = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);

        return array_values(array_filter($items, function ($item) use ($fields, $needle) {
            $haystackParts = [];
            foreach ($fields as $field) {
                if (is_callable($field)) {
                    $value = $field($item);
                } else {
                    $value = is_array($item) ? ($item[$field] ?? '') : '';
                }
                if (is_array($value)) {
                    $value = implode(' ', array_map('strval', $value));
                }
                $haystackParts[] = (string)$value;
            }
            $haystack = implode(' ', $haystackParts);
            $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
            return strpos($haystack, $needle) !== false;
        }));
    }
}
