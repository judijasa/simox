<?php

// Encode two integers into one
function cantorPair($c1, $c2) {

    return (($c1 + $c2) * ($c1 + $c2 + 1) / 2) + $c2;
}

// Decode one integer back into two integers
function cantorUnpair($c) {
    $w = floor((-1 + sqrt(1 + 8 * $c)) / 2);
    $t = ($w * $w + $w) / 2;
    $c2 = $c - $t;
    $c1 = $w - $c2;
    return [$c1, $c2];
}
?>
