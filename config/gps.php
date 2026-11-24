<?php

return [
    'max_accuracy_m'   => 50,    // isse zyada accuracy value (kharab signal) wale point reject
    'max_speed_kmh'    => 150,   // isse tez "movement" = GPS jump, reject
    'min_movement_m'   => 30,    // isse kam hilna = drift, distance me nahi judta
    'max_gap_seconds'  => 900,   // 15 min se bada gap = line nahi judegi
    'timezone' => 'Asia/Kolkata',
];