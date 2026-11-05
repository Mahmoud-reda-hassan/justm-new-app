<?php

$data = json_decode(file_get_contents("php://input"), true);

if ($data) {

    $product = $data['product'];
    $color = $data['color'];
    $size = $data['size'];
    $lat = $data['latitude'];
    $lon = $data['longitude'];

    $mapLink = "https://www.google.com/maps?q=$lat,$lon";

    $log = "$product ($color - $size) | $mapLink\n";

    file_put_contents("orders.txt", $log, FILE_APPEND);
}