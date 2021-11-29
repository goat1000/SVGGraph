<?php
/**
 * Copyright (C) 2021 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * For more information, please contact <graham@goat1000.com>
 */

namespace Goat1000\SVGGraph;

/**
 * Class for logarithmic axis with specific tick marks
 */
class AxisLogTicks extends AxisLog {

  protected $ticks;

  public function __construct($length, $max_val, $min_val, $min_unit,
    $min_space, $fit, $units_before, $units_after, $decimal_digits,
    $base, $divisions, $label_callback, $values, $ticks)
  {
    sort($ticks);
    $this->ticks = [];

    // only keep the ticks that are inside the axis bounds
    foreach($ticks as $t) {
      if($t >= $min_val && $t <= $max_val)
        $this->ticks[] = $t;
    }

    if(count($this->ticks) < 1)
      throw new \Exception('No ticks in axis range');

    parent::__construct($length, $max_val, $min_val, $min_unit,
      $min_space, $fit, $units_before, $units_after, $decimal_digits,
      $base, $divisions, $label_callback, $values);
  }

  /**
   * Returns the grid points as an array of GridPoints
   */
  public function getGridPoints($start)
  {
    if($start === null)
      return;

    $points = [];
    foreach($this->ticks as $val) {
      $position = $this->position($val);
      $position = $start + ($this->direction * $position);
      $points[] = $this->getGridPoint($position, $val);
    }

    if($this->direction < 0) {
      usort($points, function($a, $b) { return $b->position - $a->position; });
    } else {
      usort($points, function($a, $b) { return $a->position - $b->position; });
    }

    return $points;
  }
}
