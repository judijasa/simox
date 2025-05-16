<?php

function set_cursor($conn, $key, $value, $mod=0, $div=1) {
    $stmt = $conn->prepare("UPDATE cursorseq SET value = :value WHERE `key` = :key AND `mod` = :mod AND `div` = :div");
    $stmt->bindValue(':key', $key);
    $stmt->bindValue(':value', $value);
    $stmt->bindValue(':mod', $mod);
    $stmt->bindValue(':div', $div);
    $stmt->execute();
}

function get_cursor($conn, $key, $mod=0, $div=1, $init_cursor=null) {
    $stmt = $conn->prepare("SELECT value FROM cursorseq WHERE `key` = :key AND `mod` = :mod AND `div` = :div");
    $stmt->bindValue(':key', $key);
    $stmt->bindValue(':mod', $mod);
    $stmt->bindValue(':div', $div);
    $stmt->execute();
    $value = $stmt->fetchColumn();
    if($value !== false){
        return $value;
    }else{
        if($init_cursor !== null){
            set_cursor($conn, $key, $init_val, $mod, $div);
            return $init_cursor; // null
        }
        return false; // null
    }
}
?>
