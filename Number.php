<?php
/**
 * Copyright (C) 2019 Graham Breach
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
 * Class for outputting numbers
 */
class Number {
  public $value = 0;
  public $units = '';
  public $units_before = '';
  public $precision = 0;
  private $as_string = '';
  private static $default_precision = 5;
  private static $decimal_separator = '.';
  private static $thousands_separator = ',';
  
  public function __construct($value, $units = '', $units_before = '')
  {
    if(is_object($value) && get_class($value) === 'Goat1000\\SVGGraph\\Number') {
      $this->value = $value->value;
      $this->units = $value->units;
      $this->units_before = $value->units_before;
      $this->as_string = $value->as_string;
      return;
    }

    if(!is_numeric($value))
      throw new \Exception($value . ' is not a number');
    $this->value = $value;
    $this->units = $units;
    $this->units_before = $units_before;
    $this->as_string = '';
  }

  /**
   * Output for SVG values
   */
  public function __toString()
  {
    if($this->as_string !== '')
      return $this->as_string;

    $value = $this->value;
    if($value == 0) {
      $value = '0';
    } elseif($value == 1) {
      $value = '1';
    } elseif(is_int($value) || $value >= 1000 || $value <= -1000) {
      $value = sprintf('%d', $value);
    } else {
      if($this->precision)
        $value = sprintf('%.' . $this->precision . 'F', $value);
      else
        $value = sprintf('%.2F', $value);
      $value = rtrim($value, '0');
      $value = rtrim($value, '.');
    }
    $this->as_string = $value . $this->units;
    return $this->as_string;
  }

  /**
   * Sets the formatted string options
   */
  public static function setup($precision, $decimal, $thousands)
  {
    if($decimal === $thousands)
      throw new \LogicException('Decimal and thousands separators using same value. Please use different settings for "thousands" and "decimal".');

    Number::$default_precision = $precision;
    Number::$decimal_separator = $decimal;
    Number::$thousands_separator = $thousands;
  }

  /**
   * Formatted output
   */
  public function format($decimals = null, $precision = null)
  {
    $n = $this->value;
    $d = ($decimals === null ? 0 : $decimals);

    if(!is_int($n)) {
      if($precision === null)
        $precision = Number::$default_precision;

      // if there are too many zeroes before other digits, round to 0
      $e = floor(log(abs($n), 10));
      if(-$e > $precision)
        $n = 0;

      // subtract number of digits before decimal point from precision
      // for precision-based decimals
      if($decimals === null)
        $d = $precision - ($e > 0 ? $e : 0);
    }
    $s = number_format($n, $d, Number::$decimal_separator,
      Number::$thousands_separator);

    if($decimals === null && $d &&
      strpos($s, Number::$decimal_separator) !== false) {
      list($a, $b) = explode(Number::$decimal_separator, $s);
      $b1 = rtrim($b, '0');
      $s = $b1 != '' ? $a . Number::$decimal_separator . $b1 : $a;
    }
    return $this->units_before . $s . $this->units;
  }
}

