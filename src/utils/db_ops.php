<?php

function set_cursor($conn, $key, $value, $mod=0, $div=1) {
    $query = <<<EOD
        INSERT INTO cursorseq(key, value, mod, div)
        VALUES (:key, :value, :mod, :div)
        ON DUPLICATE KEY
        UPDATE value = :value;
    EOD;
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':key', $key, PDO::PARAM_STR);
    $stmt->bindValue(':value', $value, PDO::PARAM_INT);
    $stmt->bindValue(':mod', $mod, PDO::PARAM_INT);
    $stmt->bindValue(':div', $div, PDO::PARAM_INT);
    $stmt->execute();
}

function get_cursor($conn, $key, $mod=0, $div=1) {
    $stmt = $conn->prepare("SELECT value FROM cursorseq WHERE `key` = :key AND `mod` = :mod AND `div` = :div");
    $stmt->bindValue(':key', $key);
    $stmt->bindValue(':mod', $mod);
    $stmt->bindValue(':div', $div);
    $stmt->execute();
    $value = $stmt->fetchColumn();
    if($value !== false){
        return $value;
    }
}

function scan_table_in_batches($conn, $table_name, $query, $bath_size, $cursor_key, $max_time=60*50, $mod=0, $div=1) {
    /*
    Make sure the query has the overall structure:

        SELECT ... FROM ...
        WHERE id >= :curr_id AND id < :next_id
          AND abs(id) % :div = :mod
    */
    $sql = "SELECT min(id) FROM ${table_name}";
    $lower_bound = $conn->query().fetchColumn($sql);
    $curr_id = get_cursor($conn, $cursor_key, $mod=$mod, $div=$div);
    $curr_id = $curr_id ?? $lower_bound;
    $sql = "SELECT max(id) FROM ${table_name}";
    $max_id = $conn->query().fetchColumn($sql);
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
        set_cursor($conn, $cursor_key, $curr_id, $mod, $div);
    }
}
?>
