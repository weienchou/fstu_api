<?php
namespace App\Enums;

enum AirportEnum: int {
    case TPE = 1;
    case TSA = 2;

    public static function fromId(int $id): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $id) {
                return $case;
            }
        }
        return null;
    }

    public function getPlaceID(): string
    {
        return match ($this) {
            self::TPE => 'ChIJ1RXSYsCfQjQRCbG1qZC2o3A',
            self::TSA => 'ChIJWSYUpPGrQjQROop1ttwNGJM',
        };
    }

}
