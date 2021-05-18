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
 * Class for axis with specific tick marks
 */
class AxisFixedTicks extends Axis {

  protected $ticks;
  protected $unit_length = 0;

  public function __construct($length, $max, $min, $ticks, $units_before,
    $units_after, $decimal_digits, $label_callback, $values)
  {
    sort($ticks);
    $this->ticks = [];

    // only keep the ticks that are inside the axis bounds
    foreach($ticks as $t) {
      if($t >= $min && $t <= $max)
        $this->ticks[] = $t;
    }

    if(count($this->ticks) < 1)
      throw new \Exception('No ticks in axis range');

    $this->unit_length = $max - $min;
    if($this->unit_length == 0)
      throw new \Exception('Zero length axis (min >= max)');

    // min_unit = 1, min_space = 1, fit = false
    parent::__construct($length, $max, $min, 1, 1, false, $units_before,
      $units_after, $decimal_digits, $label_callback, $values);

    $this->setLength($length);
  }

  /**
   * Returns the size of a unit in grid space
   */
  public function unit()
  {
    return $this->unit_size;
  }

  /**
   * Returns the distance along the axis where 0 should be
   */
  public function zero()
  {
    return $this->zero;
  }

  /**
   * Set length, adjust scaling
   */
  public function setLength($l)
  {
    $this->length = $l;

    // these values are fixed, based on length
    $this->unit_size = $this->length / $this->unit_length;
    $this->zero = -$this->min_value * $this->unit_size;

    // not used by this class, but others expect it to be set
    $this->grid_spacing = 1;
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
      $position = $start + $this->direction * ($this->zero + $value * $this->unit_size);
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
