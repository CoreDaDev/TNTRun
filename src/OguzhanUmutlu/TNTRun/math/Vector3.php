<?php

declare(strict_types=1);

namespace OguzhanUmutlu\TNTRun\math;

class Vector3 extends \pocketmine\math\Vector3 {

    public function __toString() {
        return "$this->x,$this->y,$this->z";
    }

    public static function fromString(string $string) {
        return new Vector3((int)explode(",", $string)[0], (int)explode(",", $string)[1], (int)explode(",", $string)[2]);
    }
}
