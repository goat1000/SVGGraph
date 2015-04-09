<?php
/**
 * Copyright (C) 2013 Graham Breach
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

require_once 'SVGGraphAxis.php';

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

  public function __construct($length, $max_val, $min_val,
    $min_unit, $fit, $units_before, $units_after, $decimal_digits,
    $base, $divisions)
  {
    if($min_val == 0 || $max_val == 0)
      throw new Exception('0 value on log axis');
    if($min_val < 0 && $max_val > 0)
      throw new Exception('-ve and +ve on log axis');
    if($max_val <= $min_val && $min_unit == 0)
      throw new Exception('Zero length axis (min >= max)');
    $this->length = $length;
    $this->min_unit = $min_unit;
    $this->units_before = $units_before;
    $this->units_after = $units_after;
    $this->decimal_digits = $decimal_digits;
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

    $this->lgmin = floor(log($min_val, $this->base));
    $this->lgmax = ceil(log($max_val, $this->base));
    $this->lgmul = $this->length / ($this->lgmax - $this->lgmin);
    $this->min_value = pow($this->base, $this->lgmin);
    $this->max_value = pow($this->base, $this->lgmax);
  }

  /**
   * Returns the grid points as an associative array:
   * array($value => $position)
   */
  public function GetGridPoints($min_space, $start)
  {
    $points = array();
    $max_div = $this->length / $min_space;
    $pow_div = $this->lgmax - $this->lgmin;

    $div = 1;
    $this->grid_space = $this->length / $pow_div * $div;

    $spoints = array();
    if($this->divisions)
      $this->grid_split = $this->divisions;
    else
      $this->grid_split = $this->FindDivision($this->grid_space, $min_space);

    if($this->grid_split) {
      for($l = $this->grid_split; $l < $this->base; $l += $this->grid_split)
        $spoints[] = log($l, $this->base);
    }

    $l = $this->lgmin;
    while($l <= $this->lgmax) {
      $val = pow($this->base, $l) * ($this->negative ? -1 : 1);
      // convert to string to use as array key
      $point = $this->units_before .
        Graph::NumString($val, $this->decimal_digits) . $this->units_after;
      $pos = $this->Position($val);
      $position = $start + ($this->direction * $pos);
      $points[] = new GridPoint($position, $point, $val);

      // add in divisions between powers
      if($l < $this->lgmax) {
        foreach($spoints as $l1) {
          $val = pow($this->base, $l + $l1) * ($this->negative ? -1 : 1);
          $point = $this->units_before .
            Graph::NumString($val, $this->decimal_digits) . $this->units_after;
          $pos = $this->Position($val);
          $position = $start + ($this->direction * $pos);
          $points[] = new GridPoint($position, $point, $val);
        }
      }
      ++$l;
    }

    usort($points, ($this->direction < 0 ? 'gridpoint_rsort' : 'gridpoint_sort'));
    return $points;
  }

  /**
   * Returns the grid subdivision points as an array
   */
  public function GetGridSubdivisions($min_space, $min_unit, $start, $fixed)
  {
    $points = array();
    if($this->int_base) {
      $split = $this->FindDivision($this->grid_space, $min_space,
        $this->grid_split);
      if($split) {
        for($l = $this->lgmin; $l < $this->lgmax; ++$l) {
          for($l1 = $split; $l1 < $this->base; $l1 += $split) {
            if($this->grid_split == 0 || $l1 % $this->grid_split) {
              $p = log($l1, $this->base);
              $val = pow($this->base, $l + $p);
              $position = $start + $this->Position($val) * $this->direction;
              $points[] = new GridPoint($position, '', $val);
            }
          }
        }
      }
    }
    return $points;
  }

  /**
   * Returns the position of a value on the axis, or NULL if the position is
   * not possible
   */
  public function Position($value)
  {
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
  public function Origin()
  {
    // not the position of 0, because that doesn't exist
    return $this->negative ? $this->length : 0;
  }

  /**
   * Returns the value at a position on the axis
   */
  public function Value($position)
  {
    $p = pow($this->base, $this->lgmin + $position / $this->lgmul);
    return $p;
  }

  /**
   * Finds an even division of the given space that is >= min_space
   */
  private function FindDivision($space, $min_space, $main_division = 0)
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
  public function Zero()
  {
    if($this->negative)
      return $this->length;
    return 0;
  }
}

