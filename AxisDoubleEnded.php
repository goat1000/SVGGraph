<?php
/**
 * Copyright (C) 2013-2020 Graham Breach
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
 * Class for axis with +ve on both sides of zero
 */
class AxisDoubleEnded extends Axis {

  /**
   * Constructor calls Axis constructor with 0.5 * length
   */
  public function __construct($length, $max_val, $min_val, $min_unit, $min_space,
    $fit, $units_before, $units_after, $decimal_digits, $label_callback)
  {
    if($min_val < 0)
      throw new \Exception('Negative value for double-ended axis');
    parent::__construct($length / 2, $max_val, $min_val, $min_unit, $min_space,
      $fit, $units_before, $units_after, $decimal_digits, $label_callback, false);
  }

  /**
   * Return the full axis length, not the 1/2 length
   */
  public function getLength()
  {
    return $this->length * 2;
  }

  /**
   * Returns the distance along the axis where 0 should be
   */
  public function zero()
  {
    return $this->zero = $this->length;
  }

  /**
   * Returns the grid points as an array of GridPoints
   */
  public function getGridPoints($start)
  {
    $points = parent::getGridPoints($start);
    if($start === null)
      return;
    $new_points = [];
    $z = $this->zero();
    foreach($points as $p) {
      $new_points[] = new GridPoint($p->position + $z, $p->getText(), $p->value);
      if($p->value != 0)
        $new_points[] = new GridPoint((2 * $start) + $z - $p->position, $p->getText(), $p->value);
    }

    if($this->direction < 0) {
      usort($new_points, function($a, $b) { return $b->position - $a->position; });
    } else {
      usort($new_points, function($a, $b) { return $a->position - $b->position; });
    }
    return $new_points;
  }

  /**
   * Returns the grid subdivision points as an array
   */
  public function getGridSubdivisions($min_space, $min_unit, $start, $fixed)
  {
    $divs = parent::getGridSubdivisions($min_space, $min_unit, $start, $fixed);
    $new_divs = [];
    $z = $this->zero();
    foreach($divs as $d) {
      $new_divs[] = new GridPoint($d->position + $z, '', 0);
      $new_divs[] = new GridPoint((2 * $start) + $z - $d->position, '', 0);
    }

    return $new_divs;
  }
}

