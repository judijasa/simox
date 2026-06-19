<?php

namespace Utils\DatabaseOps;

require 'vendor/autoload.php';

use Utils\DatabaseOps\CursorSeq;

function scan_table_in_batches($conn,
    $table_name,
    $query,
    $batch_size,
    $cursor_key,
    $max_time=60*50,
    $mod=0,
    $div=1) {
    /*
    Make sure the query has the overall structure:

        SELECT ... FROM ...
        WHERE id >= :curr_id AND id < :next_id
          AND abs(id) % :div = :mod
    */
    $sql = "SELECT min(id) FROM ${table_name}";
    $stmt = $conn->query($sql);
    $lower_bound = $stmt->fetchColumn();
    $cursorseq = new CursorSeq($conn, $cursor_key);
    $curr_id = $cursorseq->get_cursor($cursor_key, $mod=$mod, $div=$div);
    $curr_id = $curr_id ?? $lower_bound;
    $sql = "SELECT max(id) FROM ${table_name}";
    $stmt = $conn->query($sql);
    $max_id = $conn->fetchColumn();
    $is_time_unlimited = ($max_time === null);
    $start_time = microtime(true);
    while($curr_id <= $max_id and ($is_time_unlimited or microtime(true) - $start_time)){
        $next_id = $curr_id + $div * $batch_size;
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':curr_id', $curr_id);
        $stmt->bindValue(':next_id', $next_id);
        $stmt->bindValue(':mod', $mod);
        $stmt->bindValue(':div', $div);
        $stmt->execute();
        $curr_id = $next_id;
        $cursorseq->set_cursor($cursor_key, $curr_id, $mod, $div);
    }
}

