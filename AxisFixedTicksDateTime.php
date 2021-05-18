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
 * Class for axis with specific tick marks and date/time keys
 */
class AxisFixedTicksDateTime extends AxisDateTime {

  protected $ticks;

  public function __construct($length, $max, $min, $ticks, $options)
  {
    // only keep the ticks that are inside the axis bounds
    $this->ticks = [];
    foreach($ticks as $tstr) {
      $t = Graph::dateConvert($tstr);
      if($t === null)
        throw new \Exception('Ticks not in correct date/time format');

      if($t >= $min && $t <= $max)
        $this->ticks[] = $t;
    }

    if(count($this->ticks) < 1)
      throw new \Exception('No ticks in axis range');

    // min_space = 1, fixed_division = null, levels = null,
    parent::__construct($length, $max, $min, 1, null, null, $options);
  }

  /**
   * Returns the grid points as an array of GridPoints
   *  if $start is NULL, just set up the grid spacing without returning points
   */
  public function getGridPoints($start)
  {
    if($start === null)
      return;

    $points = [];
    foreach($this->ticks as $value) {
      $pos = $this->position($value);
      $position = $start + ($pos * $this->direction);
      $points[] = $this->getGridPoint($position, $value);
    }

    if($this->direction < 0) {
      usort($points, function($a, $b) { return $b->position - $a->position; });
    } else {
      usort($points, function($a, $b) { return $a->position - $b->position; });
    }

    return $points;
  }
}
