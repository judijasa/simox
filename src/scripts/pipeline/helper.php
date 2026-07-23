<?php

function deduplicate_by(array $items, string $key): array
{
    $seen   = [];
    $result = [];
    foreach ($items as $item) {
        $k = $item[$key];
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $result[] = $item;
    }
    return $result;
}
