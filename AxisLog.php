<?php
/**
 * Copyright (C) 2013-2022 Graham Breach
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
 * Class for calculating logarithmic axis measurements
 */
class AxisLog extends Axis {

  protected $lgmin;
  protected $lgmax;
  protected $base = 10;
  protected $divisions;
  protected $grid_space;
  protected $grid_split = 0;
  protected $negative = false;
  protected $lgmul;
  protected $int_base = true;

  public function __construct($length, $max_val, $min_val, $min_unit,
    $min_space, $fit, $units_before, $units_after, $decimal_digits,
    $base, $divisions, $label_callback, $values)
  {
    if($min_val == 0 || $max_val == 0)
      throw new \Exception('0 value on log axis');
    if($min_val < 0 && $max_val > 0)
      throw new \Exception('-ve and +ve on log axis');
    if($max_val <= $min_val && $min_unit == 0)
      throw new \Exception('Zero length axis (min >= max)');
    $this->length = $length;
    $this->min_unit = $min_unit;
    $this->min_space = $min_space;
    $this->units_before = $units_before;
    $this->units_after = $units_after;
    $this->decimal_digits = $decimal_digits;
    $this->label_callback = $label_callback;
    $this->values = $values;
    if(is_numeric($base) && $base > 1) {
      $this->base = $base * 1.0;
      $this->int_base = $this->base == floor($this->base);
    }
    $this->uneven = false;
    if($min_val < 0) {
      $this->negative = true;
      $m = $min_val;
      $min_val = abs($max_val);
      $max_val = abs($m);
    }
    if(is_numeric($divisions))
      $this->divisions = $divisions;

    if($fit && $this->int_base) {
      // X-axis, try to use values
      $lgminf = $this->bfloor($min_val);
      $lgmaxf = $this->bceil($max_val);
      $this->lgmin = log($lgminf, $this->base);
      $this->lgmax = log($lgmaxf, $this->base);
    } else {
      // Y-axis, allow space
      $this->lgmin = floor(log($min_val, $this->base));
      $this->lgmax = ceil(log($max_val, $this->base));
    }

    // if all the values are the same, and a power of the base
    if($this->lgmax <= $this->lgmin)
      $this->lgmin -= 1.0;

    $this->lgmul = $this->length / ($this->lgmax - $this->lgmin);
    $this->min_value = pow($this->base, $this->lgmin);
    $this->max_value = pow($this->base, $this->lgmax);
  }

  /**
   * Floor to the nearest sensible number
   */
  protected function bfloor($value)
  {
    $b = floor(log($value, $this->base));
    $bf = pow($this->base, $b);
    $m = floor($value / $bf);
    return $m * $bf;
  }

  /**
   * Ceil to the nearest sensible number
   */
  protected function bceil($value)
  {
    $b = floor(log($value, $this->base));
    $bf = pow($this->base, $b);
    $m = ceil($value / $bf);
    return $m * $bf;
  }

  /**
   * Returns the grid points as an associative array:
   * array($value => $position)
   */
  public function getGridPoints($start)
  {
    if($start === null)
      return;

    $pow_div = ceil($this->lgmax) - floor($this->lgmin);
    $this->grid_space = $this->length / $pow_div;

    $this->grid_split = $this->divisions ? $this->divisions :
      $this->findDivision($this->grid_space, $this->min_space);

    $spoints = [];
    if($this->grid_split) {
      for($l = $this->grid_split; $l < $this->base; $l += $this->grid_split)
        $spoints[] = log($l, $this->base);
    }

    $points = [];
    $val = $this->min_value;
    while($val <= $this->max_value) {
      $value = $this->negative ? -$val : $val;
      $position = $this->position($value);
      $position = $start + ($this->direction * $position);
      $points[] = $this->getGridPoint($position, $value);

      // inserted at start of loop, but calculated at end
      if($val > ($this->max_value * 0.995))
        break;

      // find next point
      $l = log($val, $this->base);
      $l_floor = floor($l);
      $l_dec = $l - $l_floor;
      $l_next = 0;
      foreach($spoints as $l1) {
        if($l1 && ($l1 - $l_dec) > 0.001) {
          $l_next = $l_floor + $l1;
          break;
        }
      }
      if(!$l_next || $l_next > $this->lgmax) {
        // next full power or end of axis
        $l_next = min($l_floor + 1, $this->lgmax);
      }
      $val = pow($this->base, $l_next);
    }

    if($this->direction < 0) {
      usort($points, function($a, $b) { return $b->position - $a->position; });
    } else {
      usort($points, function($a, $b) { return $a->position - $b->position; });
    }
    return $points;
  }

  /**
   * Returns the grid subdivision points as an array
   */
  public function getGridSubdivisions($min_space, $min_unit, $start, $fixed)
  {
    $points = [];
    if($this->int_base) {
      $split = $this->findDivision($this->grid_space, $min_space,
        $this->grid_split);
      if($split) {
        for($l = $this->lgmin; $l < $this->lgmax; ++$l) {
          for($l1 = $split; $l1 < $this->base; $l1 += $split) {
            if($this->grid_split == 0 || $l1 % $this->grid_split) {
              $p = log($l1, $this->base);
              $val = pow($this->base, $l + $p);
              $position = $start + $this->position($val) * $this->direction;
              $points[] = new GridPoint($position, '', $val);
            }
          }
        }
      }
    }
    return $points;
  }

  /**
   * Returns the distance in pixels $u takes from $pos
   */
  public function measureUnits($pos, $u)
  {
    $i = Coords::parseValue($pos);
    if(!is_numeric($i['value']))
      throw new \Exception("Unable to measure $u units from '{$i['value']}'");

    $start_pos = $this->position($i['value']);
    $end_pos = $this->position($i['value'] + $u);
    return $end_pos - $start_pos;
  }

  /**
   * Returns the position of a value on the axis, or NULL if the position is
   * not possible
   */
  public function position($index, $item = null)
  {
    $value = $index;
    if($item !== null && !$this->values->associativeKeys())
      $value = $item->key;
    if($this->negative) {
      if($value >= 0)
        return null;
      $abs_value = abs($value);
      if($abs_value < $this->min_value)
        return null;
      return $this->length - (log($abs_value, $this->base) - $this->lgmin) *
        $this->lgmul;
    }
    if($value <= 0 || $value < $this->min_value)
      return null;
    return (log($value, $this->base) - $this->lgmin) * $this->lgmul;
  }

  /**
   * Returns the position of the origin
   */
  public function origin()
  {
    // not the position of 0, because that doesn't exist
    return $this->negative ? $this->length : 0;
  }

  /**
   * Returns the value at a position on the axis
   */
  public function value($position)
  {
    $p = pow($this->base, $this->lgmin + $position / $this->lgmul);
    return $p;
  }

  /**
   * Finds an even division of the given space that is >= min_space
   */
  private function findDivision($space, $min_space, $main_division = 0)
  {
    $split = 0;
    if($this->int_base) {
      $division = $main_division ? $main_division : $this->base;
      $l = $this->base - 1;
      $lgs = $space * log($l, $this->base);

      $smallest = $space - $lgs;
      if($smallest < $min_space) {
        $max_split = floor($division / 2);
        for($i = 2; $smallest < $min_space && $i <= $max_split; ++$i) {
          if($division % $i == 0) {
            // the smallest gap is the one before the next power
            $l = $this->base - $i;
            $lgs = $space * log($l, $this->base);
            $smallest = $space - $lgs;
            $split = $i;
          }
        }
        if($smallest < $min_space)
          $split = 0;
      } else {
        $split = 1;
      }
    }
    return $split;
  }

  /**
   * Not actually 0, but the position of the axis
   */
  public function zero()
  {
    if($this->negative)
      return $this->length;
    return 0;
  }
}

