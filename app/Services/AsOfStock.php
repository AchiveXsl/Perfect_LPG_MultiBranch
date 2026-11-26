<?php

namespace App\Services;

use Config\Database;

/**
 * Stock as of the end of the branch System Date (owner decision 2026-11-26).
 *
 * cylinder_units holds the current state of every cylinder. When the branch works on a System Date
 * other than today, stock screens must show the state at the end of that date instead. That state is
 * rebuilt per cylinder from inventory_movements up to the date:
 *   filled_cylinder in - out  > 0  -> filled in the shop
 *   empty_cylinder  in - out  > 0  -> empty in the shop
 *   otherwise                      -> out of the shop (sold, with a customer, in transit)
 *   gas_kg          in - out       -> gas in the cylinder
 * Cylinders without any movement keep their current state when they existed by then.
 *
 * unitsTable() returns a table name with the columns of cylinder_units, so a screen only swaps the
 * table name: 'cylinder_units' when the branch works on today, otherwise a temporary table built once
 * per request. A temporary table cannot be opened twice in one query, so do not self-join it.
 */
class AsOfStock
{
    /** @var array<string,string> */
    protected static array $built = [];

    public static function unitsTable(?int $locationId = null): string
    {
        $locationId = $locationId ?? BranchContext::currentId();
        if ($locationId <= 0 || !BranchContext::isShifted($locationId)) {
            return 'cylinder_units';
        }
        $at = BranchContext::today($locationId) . ' 23:59:59';
        $key = $locationId . '|' . $at;
        if (isset(self::$built[$key])) {
            return self::$built[$key];
        }
        $db = Database::connect();
        $name = 'asof_units_' . $locationId;
        $loc = (int) $locationId;
        $atSql = $db->escape($at);
        $db->query('DROP TEMPORARY TABLE IF EXISTS ' . $name);
        $db->query('CREATE TEMPORARY TABLE ' . $name . ' AS
            SELECT u.* FROM cylinder_units u
            WHERE EXISTS (SELECT 1 FROM inventory_movements m WHERE m.cylinder_unit_id = u.id AND m.location_id = ' . $loc . ' AND m.movement_at <= ' . $atSql . ')
               OR (u.location_id = ' . $loc . ' AND u.created_at <= ' . $atSql . '
                   AND NOT EXISTS (SELECT 1 FROM inventory_movements z WHERE z.cylinder_unit_id = u.id))');
        $db->query('ALTER TABLE ' . $name . ' MODIFY status VARCHAR(20) NOT NULL');
        $db->query('UPDATE ' . $name . ' t JOIN (
                SELECT m.cylinder_unit_id id,
                    SUM(CASE WHEN m.inventory_type = \'filled_cylinder\' THEN IF(m.direction = \'in\', m.quantity, -m.quantity) ELSE 0 END) f,
                    SUM(CASE WHEN m.inventory_type = \'empty_cylinder\' THEN IF(m.direction = \'in\', m.quantity, -m.quantity) ELSE 0 END) e,
                    SUM(CASE WHEN m.inventory_type = \'gas_kg\' THEN IF(m.direction = \'in\', m.quantity, -m.quantity) ELSE 0 END) g
                FROM inventory_movements m
                WHERE m.location_id = ' . $loc . ' AND m.movement_at <= ' . $atSql . ' AND m.cylinder_unit_id IS NOT NULL AND m.direction IN (\'in\', \'out\')
                GROUP BY m.cylinder_unit_id
            ) a ON a.id = t.id
            SET t.location_id = ' . $loc . ',
                t.status = CASE WHEN a.f > 0.5 THEN \'filled\' WHEN a.e > 0.5 THEN \'empty\' ELSE \'out\' END,
                t.gas_weight_kg = CASE WHEN a.f > 0.5 THEN GREATEST(a.g, 0) ELSE 0 END');
        return self::$built[$key] = $name;
    }

    /** Text for screens: "as of 20-Nov-2026" when shifted, '' on today. */
    public static function label(?int $locationId = null): string
    {
        return BranchContext::isShifted($locationId) ? 'as of end of ' . date('d-M-Y', strtotime(BranchContext::today($locationId))) : '';
    }
}
