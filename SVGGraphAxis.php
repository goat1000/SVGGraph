<?php
/**
 * Copyright (C) 2011-2015 Graham Breach
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

/**
 * Class for calculating axis measurements
 */
class Axis {

  protected $length;
  protected $max_value;
  protected $min_value;
  protected $unit_size;
  protected $min_unit;
  protected $fit;
  protected $zero;
  protected $units_before;
  protected $units_after;
  protected $decimal_digits;
  protected $uneven = false;
  protected $rounded_up = false;
  protected $direction = 1;
  protected $label_callback = false;

  public function __construct($length, $max_val, $min_val, $min_unit, $fit,
    $units_before, $units_after, $decimal_digits, $label_callback)
  {
    if($max_val <= $min_val && $min_unit == 0)
      throw new Exception('Zero length axis (min >= max)');
    $this->length = $length;
    $this->max_value = $max_val;
    $this->min_value = $min_val;
    $this->min_unit = $min_unit;
    $this->fit = $fit;
    $this->units_before = $units_before;
    $this->units_after = $units_after;
    $this->decimal_digits = $decimal_digits;
    $this->label_callback = $label_callback;
  }

  /**
   * Allow length adjustment
   */
  public function SetLength($l)
  {
    $this->length = $l;
  }

  /**
   * Returns TRUE if the number $n is 'nice'
   */
  private function nice($n, $m)
  {
    if(is_integer($n) && ($n % 100 == 0 || $n % 10 == 0 || $n % 5 == 0))
      return true;

    if($this->min_unit) {
      $d = $n / $this->min_unit;
      if($d != floor($d))
        return false;
    }
    $s = (string)$n;
    if(preg_match('/^\d(\.\d{1,1})$/', $s))
      return true;
    if(preg_match('/^\d+$/', $s))
      return true;

    return false;
  }


  /**
   * Subdivide when the divisions are too large
   */
  private function sub_division($length, $min, &$count, &$neg_count,
    &$magnitude)
  {
    $m = $magnitude * 0.5;
    $magnitude = $m;
    $count *= 2;
    $neg_count *= 2;
  }

  /**
   * Determine the axis divisions
   */
  private function find_division($length, $min, &$count, &$neg_count,
    &$magnitude)
  {
    if($length / $count >= $min)
      return;

    $c = $count - 1;
    $inc = 0;
    while($c > 1) {
      $m = ($count + $inc) / $c;
      $l = $length / $c;
      $test_below = $neg_count ? $c * $neg_count / $count : 1;
      if($this->nice($m, $count + $inc)) {
        if($l >= $min && $test_below - floor($test_below) == 0) {
          $magnitude *= ($count + $inc) / $c;
          $neg_count *= $c / $count;
          $count = $c;
          return;
        }
        --$c;
        $inc = 0;
      } elseif(!$this->fit && $count % 2 == 1 && $inc == 0) {
        $inc = 1;
      } else {
        --$c;
        $inc = 0;
      }
    }

    // try to balance the +ve and -ve a bit 
    if($neg_count) {
      $c = $count + 1;
      $p_count = $count - $neg_count;
      if($p_count > $neg_count && ($neg_count == 1 || $c % $neg_count))
        ++$neg_count;
      ++$count;
    }
  }

  /**
   * Sets the bar style (which means an extra unit)
   */
  public function Bar()
  {
    if(!$this->rounded_up) {
      $this->max_value += $this->min_unit;
      $this->rounded_up = true;
    }
  }

  /**
   * Sets the direction of axis points
   */
  public function Reverse()
  {
    $this->direction = -1;
  }

  /**
   * Returns the grid spacing
   */
  protected function Grid($min)
  {
    $this->uneven = false;
    $negative = $this->min_value < 0;
    $min_sub = max($min, $this->length / 200);

    if($this->min_value == $this->max_value)
      $this->max_value += $this->min_unit;
    $scale = $this->max_value - $this->min_value;

    // get magnitude from greater of |+ve|, |-ve|
    $abs_min = abs($this->min_value);
    $magnitude = max(pow(10, floor(log10($scale))), $this->min_unit);
    if($this->fit) {
      $count = ceil($scale / $magnitude);
    } else {
      $count = ceil($this->max_value / $magnitude) - 
        floor($this->min_value / $magnitude);
    }

    if($count <= 5 && $magnitude > $this->min_unit) {
      $magnitude *= 0.1;
      $count = ceil($this->max_value / $magnitude) - 
        floor($this->min_value / $magnitude);
    }

    $neg_count = ceil($abs_min / $magnitude);
    $this->find_division($this->length, $min_sub, $count, $neg_count,
      $magnitude);
    $grid = $this->length / $count;

    // guard this loop in case the numbers are too awkward to fit
    $guard = 10;
    while($grid < $min && --$guard) {
      $this->find_division($this->length, $min_sub, $count, $neg_count,
        $magnitude);
      $grid = $this->length / $count;
    }
    if($guard == 0) {
      // could not find a division
      while($grid < $min && $count > 1) {
        $count *= 0.5;
        $neg_count *= 0.5;
        $magnitude *= 2;
        $grid = $this->length / $count;
        $this->uneven = true;
      }

    } elseif(!$this->fit && $magnitude > $this->min_unit &&
      $grid / $min > 2) {
      // division still seems a bit coarse
      $this->sub_division($this->length, $min_sub, $count, $neg_count,
        $magnitude);
      $grid = $this->length / $count;
    }

    $this->unit_size = $this->length / ($magnitude * $count);
    $this->zero = $negative ? $neg_count * $grid :
      -$this->min_value * $grid / $magnitude;

    return $grid;
  }

  /**
   * Returns the size of a unit in grid space
   */
  public function Unit()
  {
    if(!isset($this->unit_size))
      $this->Grid(1);

    return $this->unit_size;
  }

  /**
   * Returns the distance along the axis where 0 should be
   */
  public function Zero()
  {
    if(!isset($this->zero))
      $this->Grid(1);

    return $this->zero;
  }

  /**
   * Returns TRUE if the grid spacing does not fill the grid
   */
  public function Uneven()
  {
    return $this->uneven;
  }

  /**
   * Returns the position of a value on the axis
   */
  public function Position($value)
  {
    return $this->Zero() + ($value * $this->Unit());
  }

  /**
   * Returns the position of the origin
   */
  public function Origin()
  {
    // for a linear axis, it should be the zero point
    return $this->Zero();
  }

  /**
   * Returns the value at a position on the axis
   */
  public function Value($position)
  {
    return ($position - $this->Zero()) / $this->Unit();
  }

  /**
   * Return the before units text
   */
  public function BeforeUnits()
  {
    return $this->units_before;
  }

  /**
   * Return the after units text
   */
  public function AfterUnits()
  {
    return $this->units_after;
  }

  /**
   * Returns the grid points as an array of GridPoints
   */
  public function GetGridPoints($min_space, $start)
  {
    $spacing = $this->Grid($min_space);
    $c = $pos = 0;
    $dlength = $this->length + $spacing * 0.5;
    $points = array();

    if($dlength / $spacing > 10000) {
      $pcount = $dlength / $spacing;
      throw new Exception("Too many grid points ({$this->min_value}->{$this->max_value} = {$pcount})");
    }

    while($pos < $dlength) {
      // convert to string to use as array key
      $value = ($pos - $this->zero) / $this->unit_size;
      if(is_callable($this->label_callback)) {
        $text = call_user_func($this->label_callback, $value);
      } else {
        $text = $this->units_before .
          Graph::NumString($value, $this->decimal_digits) . $this->units_after;
      }
      $position = $start + ($this->direction * $pos);
      $points[] = new GridPoint($position, $text, $value);
      $pos = ++$c * $spacing;
    }
    // uneven means the divisions don't fit exactly, so add the last one in
    if($this->uneven) {
      $pos = $this->length - $this->zero;
      $value = $pos / $this->unit_size;
      if(is_callable($this->label_callback)) {
        $text = call_user_func($this->label_callback, $value);
      } else {
        $text = $this->units_before .
          Graph::NumString($value, $this->decimal_digits) . $this->units_after;
      }
      $position = $start + ($this->direction * $this->length);
      $points[] = new GridPoint($position, $text, $value);
    }

    // using 'GridPoint::sort' silently fails in PHP 5.1.x
    usort($points, ($this->direction < 0 ? 'gridpoint_rsort' : 'gridpoint_sort'));
    $this->grid_spacing = $spacing;
    return $points;
  }

  /**
   * Returns the grid subdivision points as an array
   */
  public function GetGridSubdivisions($min_space, $min_unit, $start, $fixed)
  {
    if(!$this->grid_spacing)
      throw new Exception('grid_spacing not set');

    $subdivs = array();
    $spacing = $this->FindSubdiv($this->grid_spacing, $min_space, $min_unit,
      $fixed);
    if(!$spacing)
      return $subdivs;

    $c = $pos1 = $pos2 = 0;
    $this;
    $pos1 = $c * $this->grid_spacing;
    while($pos1 + $spacing < $this->length) {
      $d = 1;
      $pos2 = $d * $spacing;
      while($pos2 < $this->grid_spacing) {
        $subdivs[] = new GridPoint($start + (($pos1 + $pos2) * $this->direction), '', 0);
        ++$d;
        $pos2 = $d * $spacing;
      }
      ++$c;
      $pos1 = $c * $this->grid_spacing;
    }
    return $subdivs;
  }

  /**
   * Find the subdivision size
   */
  private function FindSubdiv($grid_div, $min, $min_unit, $fixed)
  {
    if(is_numeric($fixed))
      return $this->unit_size * $fixed;

    $D = $grid_div / $this->unit_size;  // D = actual division size
    $min = max($min, $min_unit * $this->unit_size); // use the larger minimum value
    $max_divisions = (int)floor($grid_div / $min);

    // can we subdivide at all?
    if($max_divisions <= 1)
      return null;

    // convert $D to an integer in the 100's range
    $D1 = (int)round(100 * (pow(10,-floor(log10($D)))) * $D);
    for($divisions = $max_divisions; $divisions > 1; --$divisions) {
      // if $D1 / $divisions is not an integer, $divisions is no good
      $dq = $D1 / $divisions;
      if($dq - floor($dq) == 0)
        return $grid_div / $divisions;
    }
    return null;
  }

}

/**
 * Class for axis grid points
 */
class GridPoint {

  public $position;
  public $text;
  public $value;

  public function __construct($position, $text, $value)
  {
    $this->position = $position;
    $this->text = $text;
    $this->value = $value;
  }

  public static function sort($a, $b)
  {
    return $a->position - $b->position;
  }

  public static function rsort($a, $b)
  {
    return $b->position - $a->position;
  }

}

function gridpoint_sort($a, $b)
{
  return $a->position - $b->position;
}

function gridpoint_rsort($a, $b)
{
  return $b->position - $a->position;
}

