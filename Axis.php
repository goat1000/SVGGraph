<?php
/**
 * Copyright (C) 2011-2022 Graham Breach
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
 * Class for calculating axis measurements
 */
class Axis {

  protected $length;
  protected $max_value;
  protected $min_value;
  protected $unit_size;
  protected $min_unit;
  protected $min_space;
  protected $fit;
  protected $zero;
  protected $units_before;
  protected $units_after;
  protected $decimal_digits;
  protected $uneven = false;
  protected $rounded_up = false;
  protected $direction = 1;
  protected $label_callback = false;
  protected $values = false;
  protected $tightness = 1;
  protected $grid_spacing;

  public function __construct($length, $max_val, $min_val, $min_unit, $min_space,
    $fit, $units_before, $units_after, $decimal_digits, $label_callback, $values)
  {
    if($max_val <= $min_val && $min_unit == 0)
      throw new \Exception('Zero length axis (min >= max)');
    $this->length = $length;
    $this->max_value = $max_val;
    $this->min_value = $min_val;
    $this->min_unit = $min_unit;
    $this->min_space = $min_space;
    $this->fit = $fit;
    $this->units_before = $units_before;
    $this->units_after = $units_after;
    $this->decimal_digits = $decimal_digits;
    $this->label_callback = $label_callback;
    $this->values = $values;
  }

  /**
   * Returns min for an axis based on its min and max values
   */
  public static function calcMinimum($min_value, $max_value, $allow_zero,
    $prefer_zero)
  {
    if($allow_zero && $prefer_zero) {
      if($min_value > 0)
        return 0;
      if($max_value < 0)
        $max_value = 0;
    }

    if($min_value > 0) {
      $mag = floor(log10($min_value));
      if($allow_zero) {
        $mag1 = floor(log10($max_value));
        if($mag1 > $mag)
          return 0;
      }
      $d = pow(10, $mag);
      $min_value = floor($min_value / $d) * $d;
    }
    return $min_value;
  }

  /**
   * Returns max for an axis based on its min and max values
   */
  public static function calcMaximum($min_value, $max_value, $allow_zero,
    $prefer_zero)
  {
    if($max_value >= 0)
      return $max_value;

    // instead of duplicating code, negate values and pass to calcMinimum()
    $neg_max = Axis::calcMinimum(-$max_value, -$min_value, $allow_zero,
      $prefer_zero);
    if($neg_max > 0)
      return -$neg_max;
    return 0;
  }

  /**
   * Allow length adjustment
   */
  public function setLength($l)
  {
    $this->length = $l;
  }

  /**
   * Returns the axis length
   */
  public function getLength()
  {
    return $this->length;
  }

  /**
   * Sets the tightness option
   */
  public function setTightness($t)
  {
    $this->tightness = $t;
  }

  /**
   * Returns a score for "niceness"
   */
  private function nice($n)
  {
    if($this->min_unit) {
      $d = $n / $this->min_unit;
      if($d != floor($d))
        return 0;
    }

    // convert to string
    $nn = new Number($n);
    $nn->precision = 5;
    $s = (string)$nn;

    $niceness = [
      '0.1' => 50,
      '0.5' => 40,
      '0.2' => 25,
      '2.5' => 25,
      '1.5' => 20,
      '0.3' => 10,
      '0.4' => 10,
      '1' => 100,
      '5' => 95,
      '2' => 95,
      '3' => 45,
      '4' => 40,
      '6' => 30,
      '8' => 20,
      '7' => 10,
      '9' => 5,
      '25' => 95,
      '15' => 40,
      '75' => 30,
    ];

    $digits = $s;
    if(preg_match('/^([1-9]{1,2})(0*)$/', $s, $parts)) {
      // integer with one or two non-zero digit
      $digits = $parts[1];
    } elseif(preg_match('/^0\.(0+)([1-9]{1,2})$/', $s, $parts)) {
      // float with leading zeroes
      $digits = $parts[2];
    }

    return isset($niceness[$digits]) ? $niceness[$digits] : 0;
  }

  /**
   * Determine the axis divisions
   */
  private function findDivision($length, $min, &$count, &$neg_count, &$magnitude)
  {
    if($this->tightness && $length / $count >= $min) {
      return;
    }

    $c = $count - 1;
    $inc = 0;

    // $max_inc is how many extra steps the axis can grow by
    if($this->fit)
      $max_inc = 0;
    else
      $max_inc = $count / ($this->tightness ? 5 : 2);

    $candidates = [];
    while($c > 1) {
      $m = ($count + $inc) / $c;
      $new_magnitude = $m * $magnitude;
      $l = $length / $c;
      $nc = $neg_count;

      $accept = false;
      $niceness = $this->nice($new_magnitude);
      if($niceness > 0 && $l >= $min) {
        $accept = true;

        // negative values mean an extra check
        if($nc) {
          $accept = false;
          $nm = $nc / $m;

          if(floor($nm) === $nm) {
            $nc = $nm;
            $accept = true;
          } else {

            // negative section doesn't divide cleanly, try adding from $inc
            if($inc) {
              for($i = 1; $i <= $inc; ++$i) {
                $cc = $nc + $i;
                $nm = ($nc + $i) / $m;

                if(floor($nm) === $nm) {
                  $nc = $nm;
                  $accept = true;
                  break;
                }
              }
            }
          }
        }
      }

      if($accept) {
        $pos = ($c - $nc) * $new_magnitude;
        $neg = $nc * $new_magnitude;
        $pos_niceness = $this->nice($pos);
        $neg_niceness = $this->nice($neg);

        if($this->tightness || $neg_niceness || $pos_niceness) {
          // this division is acceptable, cost and store it
          $cost = $m;
          if($this->tightness) {
            $cost += $inc * 1.5;
          } else {
            // increasing the length is not as costly
            $cost += $inc * 0.5;

            // reduce cost for nicer divisions
            $cost -= $niceness / 50;

            // adjust cost for axis ends
            if($nc) {
              if($neg_niceness) {
                $cost -= $neg_niceness / 100;
                if($pos_niceness)
                  $cost -= $pos_niceness / 100;
              } else {
                // poor choice
                $cost += 3;
              }
            } elseif($pos_niceness) {
              $cost -= $pos_niceness / 100;
            }
          }

          $candidate = [
            // usort requires ints to work properly
            'cost' => intval(1e5 * $cost),
            'magnitude' => $new_magnitude,
            'count' => $c,
            'neg_count' => $nc,

            // these are only used for tuning / debugging
            'm' => $m,
            'real_cost' => $cost,
            'max_pos' => $pos,
            'max_neg' => $neg,
            'nice_mag' => $niceness,
            'nice_pos' => $pos_niceness,
            'nice_neg' => $neg_niceness,
          ];

          $candidates[] = $candidate;
        }
      }

      if($inc < $max_inc) {
        // increase the number of base divisions
        ++$inc;
        continue;
      }

      --$c;
      $inc = 0;
    }

    if(empty($candidates))
      return;

    usort($candidates, function($a, $b) { return $a['cost'] - $b['cost']; });
    $winner = $candidates[0];
    $magnitude = $winner['magnitude'];
    $count = $winner['count'];
    $neg_count = $winner['neg_count'];
  }

  /**
   * Sets the bar style (which means an extra unit)
   */
  public function bar()
  {
    if(!$this->rounded_up) {
      $this->max_value += $this->min_unit;
      $this->rounded_up = true;
    }
  }

  /**
   * Sets the direction of axis points
   */
  public function reverse()
  {
    $this->direction = -1;
  }

  /**
   * Returns TRUE if the axis is reversed
   */
  public function reversed()
  {
    return $this->direction < 0;
  }

  /**
   * Returns the grid spacing
   */
  protected function grid()
  {
    $min_space = $this->min_space;
    $this->uneven = false;
    $negative = $this->min_value < 0;
    $min_sub = max($min_space, $this->length / 200);

    if($this->min_value == $this->max_value)
      $this->max_value += $this->min_unit;
    $scale = $this->max_value - $this->min_value;

    $abs_min = abs($this->min_value);
    $magnitude = max(pow(10, floor(log10($scale))), $this->min_unit);
    if($this->min_value > 0 || $this->fit) {
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

    $neg_count = $this->min_value < 0 ? ceil($abs_min / $magnitude) : 0;
    $this->findDivision($this->length, $min_sub, $count, $neg_count,
      $magnitude);
    $grid = $this->length / $count;

    // guard this loop in case the numbers are too awkward to fit
    $guard = 10;
    while($grid < $min_space && --$guard) {
      $this->findDivision($this->length, $min_sub, $count, $neg_count,
        $magnitude);
      $grid = $this->length / $count;
    }
    if($guard == 0) {
      // could not find a division
      while($grid < $min_space && $count > 1) {
        $count *= 0.5;
        $neg_count *= 0.5;
        $magnitude *= 2;
        $grid = $this->length / $count;
        $this->uneven = true;
      }
    }

    $this->unit_size = $this->length / ($magnitude * $count);
    $this->zero = $negative ? $neg_count * $grid :
      -$this->min_value * $grid / $magnitude;

    return $grid;
  }

  /**
   * Returns the size of a unit in grid space
   */
  public function unit()
  {
    if(!isset($this->unit_size))
      $this->grid();

    return $this->unit_size;
  }

  /**
   * Returns the distance along the axis where 0 should be
   */
  public function zero()
  {
    if(!isset($this->zero))
      $this->grid();

    return $this->zero;
  }

  /**
   * Returns TRUE if the grid spacing does not fill the grid
   */
  public function uneven()
  {
    return $this->uneven;
  }

  /**
   * Returns the distance in pixels $u takes from $pos
   */
  public function measureUnits($pos, $u)
  {
    // on an ordinary axis this works fine
    $l = $this->position($u);
    $zero = $this->position(0);
    return $l - $zero;
  }

  /**
   * Returns the position of a value on the axis
   */
  public function position($index, $item = null)
  {
    $value = $index;
    if($item !== null && !$this->values->associativeKeys())
      $value = $item->key;
    if(!is_numeric($value))
      return null;
    return $this->zero() + ($value * $this->unit());
  }

  /**
   * Returns the position of an associative key, if possible
   */
  public function positionByKey($key)
  {
    if($this->values && $this->values->associativeKeys()) {

      // only need to look through dataset 0 because multi-dataset graphs
      // convert to structured
      $index = 0;
      foreach($this->values[0] as $item) {
        if($item->key == $key) {
          return $this->zero() + ($index * $this->unit());
        }
        ++$index;
      }
    }
    return null;
  }

  /**
   * Returns the position of the origin
   */
  public function origin()
  {
    // for a linear axis, it should be the zero point
    return $this->zero();
  }

  /**
   * Returns the value at a position on the axis
   */
  public function value($position)
  {
    return ($position - $this->zero()) / $this->unit();
  }

  /**
   * Return the before units text
   */
  public function beforeUnits()
  {
    return $this->units_before;
  }

  /**
   * Return the after units text
   */
  public function afterUnits()
  {
    return $this->units_after;
  }

  /**
   * Returns a single GridPoint
   */
  protected function getGridPoint($position, $value)
  {
    $key = $text = $value;
    $item = null;

    if($this->values) {

      // try structured data first
      $item = $this->values->getItem($value);
      if($item !== null && $this->values->getData($value, 'axis_text', $text))
        return new GridPoint($position, $text, $value, $item);

      // use the key if it is not the same as the value
      $key = $this->values->getKey($value);
    }

    // if there is a callback, use it
    if(is_callable($this->label_callback)) {
      // assoc keys should have integer indices
      if($this->values && $this->values->associativeKeys())
        $value = (int)round($value);
      $text = call_user_func($this->label_callback, $value, $key);
      return new GridPoint($position, $text, $value, $item);
    }

    if($key !== $value)
      return new GridPoint($position, $key, $value, $item);

    $n = new Number($value, $this->units_after, $this->units_before);
    $text = $n->format($this->decimal_digits);
    return new GridPoint($position, $text, $value, $item);
  }

  /**
   * Returns the grid points as an array of GridPoints
   *  if $start is NULL, just set up the grid spacing without returning points
   */
  public function getGridPoints($start)
  {
    $this->grid_spacing = $spacing = $this->grid();
    $dlength = $this->length + $spacing * 0.5;
    if($dlength / $spacing > 10000) {
      $pcount = $dlength / $spacing;
      throw new \Exception('Too many grid points (' . $this->min_value . '->' .
        $this->max_value . ' = ' . $pcount . ' points @ ' . $spacing . 'px separation)');
    }
    if($start === null)
      return;

    $c = $pos = 0;
    $points = [];
    while($pos < $dlength) {
      $value = ($pos - $this->zero) / $this->unit_size;
      $position = $start + ($this->direction * $pos);
      $points[] = $this->getGridPoint($position, $value);
      $pos = ++$c * $spacing;
    }
    // uneven means the divisions don't fit exactly, so add the last one in
    if($this->uneven) {
      $pos = $this->length - $this->zero;
      $value = $pos / $this->unit_size;
      $position = $start + ($this->direction * $this->length);
      $points[] = $this->getGridPoint($position, $value);
    }

    if($this->direction < 0) {
      usort($points, function($a, $b) { return $b->position - $a->position; });
    } else {
      usort($points, function($a, $b) { return $a->position - $b->position; });
    }

    $this->grid_spacing = $spacing;
    return $points;
  }

  /**
   * Returns the grid subdivision points as an array
   */
  public function getGridSubdivisions($min_space, $min_unit, $start, $fixed)
  {
    if(!$this->grid_spacing)
      throw new \Exception('grid_spacing not set');

    $subdivs = [];
    $spacing = $this->findSubdiv($this->grid_spacing, $min_space, $min_unit,
      $fixed);
    if(!$spacing)
      return $subdivs;

    $c = $pos1 = $pos2 = 0;
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
  private function findSubdiv($grid_div, $min, $min_unit, $fixed)
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

