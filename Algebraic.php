<?php
/**
 * Copyright (C) 2023 Graham Breach
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
 * Class for algebraic functions
 */
class Algebraic {

  private $type = 'straight';
  private $coeffs = [0, 1];

  public function __construct($type)
  {
    $this->type = $type;
  }

  /**
   * Sets the coefficients in order, lowest power first
   */
  public function setCoefficients(array $coefficients)
  {
    $this->coeffs = $coefficients;
  }

  /**
   * Returns the y value for a + bx + cx^2 ...
   */
  public function __invoke($x)
  {
      $val = 0;
      foreach($this->coeffs as $p => $c) {
        switch($p) {
        case 0: $val = bcadd($val, $c);
          break;
        case 1: $val = bcadd($val, bcmul($c, $x));
          break;
        default:
          $val = bcadd($val, bcmul($c, bcpow($x, $p)));
          break;
        }
      }
      return $val;
  }

  /**
   * Creates a row of the vandermonde matrix
   */
  public function vandermonde($x)
  {
    $t = $this->type;
    return $this->{$t}($x);
  }

  private function straight($x)
  {
    return [$x];
  }

  private function quadratic($x)
  {
    return [$x, bcmul($x, $x)];
  }

  private function cubic($x)
  {
    $res = [$x, bcmul($x, $x)];
    $res[] = bcmul($res[1], $x);
    return $res;
  }

  private function quartic($x)
  {
    $res = $this->cubic($x);
    $res[] = bcmul($res[1], $res[1]);
    return $res;
  }

  private function quintic($x)
  {
    $res = $this->cubic($x);
    $res[] = bcmul($res[1], $res[1]);
    $res[] = bcmul($res[1], $res[2]);
    return $res;
  }
}

