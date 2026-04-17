<?php
namespace Models;

use Core\Model;

class ApiModel extends Model
{
    public static function getStates(int $countryId): array
    {
        return static::rows('SELECT id, name FROM states WHERE country_id = ? ORDER BY name', [$countryId]);
    }

    public static function getDistricts(int $stateId): array
    {
        return static::rows('SELECT id, name FROM districts WHERE state_id = ? ORDER BY name', [$stateId]);
    }
}
