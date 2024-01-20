<?php

function set_cursor($conn, $key, $value) {
    $stmt = $conn->prepare("UPDATE cursorseq SET value = :value WHERE `key` = :key");
    $stmt->bindValue(':key', $key);
    $stmt->bindValue(':value', $value);
    $stmt->execute();
}

function get_cursor($conn, $key) {
    $stmt = $conn->prepare("SELECT value FROM cursorseq WHERE `key` = :key");
    $stmt->bindValue(':key', $key);
    $stmt->execute();
    return $stmt->fetchColumn();
}
?>
