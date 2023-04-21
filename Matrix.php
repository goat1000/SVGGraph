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
 * Class for matrix maths
 */
class Matrix {
  private $rows = 0;
  private $cols = 0;
  private $data = [];
  const MAX_SIZE = 100000;

  public function __construct($r, $c)
  {
    if(!is_int($c) || !is_int($r) || $c < 1 || $r < 1)
      throw new \InvalidArgumentException("{$r}\u{00d7}{$c} matrix size invalid");
    if($c * $r > Matrix::MAX_SIZE)
      throw new \InvalidArgumentException("{$r}\u{00d7}{$c} matrix too large");

    $this->rows = $r;
    $this->cols = $c;
    $this->data = array_fill(0, $c * $r, 0);
  }

  /**
   * Returns the number of rows and cols in an array
   */
  public function dimensions()
  {
    return [$this->rows, $this->cols];
  }

  /**
   * Returns the size as a string
   */
  public function size()
  {
    return $this->rows . "\u{00d7}" . $this->cols;
  }

  /**
   * Element access
   */
  public function &__invoke($r, $c, $v = null)
  {
    if($c < 0 || $r < 0 || $c >= $this->cols || $r >= $this->rows)
      throw new \InvalidArgumentException("({$r},{$c}) out of range of " .
        $this->size() . " matrix");

    $item = $this->cols * $r + $c;
    if($v !== null) {
      if(!is_numeric($v))
        throw new \InvalidArgumentException("Matrix values must be numeric");
      if(is_string($v))
        $this->data[$item] = $v;
      elseif(is_int($v))
        $this->data[$item] = (string)$v;
      else
        $this->data[$item] = sprintf("%.40F", $v);
    }
    return $this->data[$item];
  }

  /**
   * Fill the array with values (row-major order)
   */
  public function load(array $values)
  {
    for($i = 0; $i < $this->rows * $this->cols; ++$i) {
      $value = $values[$i];
      if(isset($value) && is_numeric($value)) {
        $this->data[$i] = is_int($value) ? (string)$value :
          sprintf("%.40F", $value);
      }
    }
  }

  /**
   * Sets the identity matrix
   */
  public function identity()
  {
    if($this->rows != $this->cols)
      throw new \Exception($this->size() . " not a square matrix");
    $this->data = array_fill(0, $this->rows * $this->cols, 0);
    for($i = 0; $i < $this->rows; ++$i)
      $this->data[($i * $this->rows) + $i] = 1;
  }

  /**
   * Return the transpose of the matrix
   */
  public function transpose()
  {
    $tc = $this->rows;
    $tr = $this->cols;
    $result = new Matrix($tr, $tc);

    // row or column vector = same data when transposed
    if($tc == 1 || $tr == 1) {
      $result->data = $this->data;
      return $result;
    }

    for($c = 0; $c < $tc; ++$c) {
      for($r = 0; $r < $tr; ++$r) {
        $result($r, $c, $this($c, $r));
      }
    }
    return $result;
  }

  /**
   * Add two matrices
   */
  public function add(Matrix $m, $bcscale = 50)
  {
    if($m->rows != $this->rows || $m->cols != $this->cols)
      throw new \InvalidArgumentException("Cannot add " . $this->size() .
        " and " . $m->size() . " matrices.");

    $old_scale = bcscale($bcscale);
    $result = new Matrix($this->cols, $this->rows);
    foreach($this->data as $k => $value)
      $result->data[$k] = bcadd($value, $m->data[$k]);

    bcscale($old_scale);
    return $result;
  }

  /**
   * Subtract a matrix
   */
  public function subtract(Matrix $m, $bcscale = 50)
  {
    if($m->rows != $this->rows || $m->cols != $this->cols)
      throw new \InvalidArgumentException("Cannot subtract " . $m->size() .
        " matrix from " . $this->size() . " matrix.");

    $old_scale = bcscale($bcscale);
    $result = new Matrix($this->cols, $this->rows);
    foreach($this->data as $k => $value)
      $result->data[$k] = bcsub($value, $m->data[$k]);

    bcscale($old_scale);
    return $result;
  }

  /**
   * Multiplication by a matrix
   */
  public function multiply(Matrix $m1, $bcscale = 50)
  {
    if($this->cols != $m1->rows)
      throw new \InvalidArgumentException("Cannot multiply " . $this->size() .
        " matrix by " . $m1->size() . " matrix.");
    $m = $this->rows;
    $n = $this->cols;
    $p = $m1->cols;

    $old_scale = bcscale($bcscale);
    $result = new Matrix($m, $p);
    for($i = 0; $i < $m; ++$i) {
      for($j = 0; $j < $p; ++$j) {
        $value = "0";
        for($k = 0; $k < $n; ++$k) {
          $value = bcadd($value, bcmul($this($i, $k), $m1($k, $j)));
        }
        $result($i, $j, $value);
      }
    }

    bcscale($old_scale);
    return $result;
  }

  /**
   * Gaussian elimination
   */
  public function gaussian($bcscale = 50)
  {
    $old_scale = bcscale($bcscale);

    $argmax = function($a, $b, $m, $col) {
      $max = 0;
      $max_i = 0;
      for($i = $a; $i < $b; ++$i) {
        $value = $m($a, $col);
        if(bccomp($value, "0") == -1)
          $value = bcmul($value, "-1");
        if(bccomp($value, $max) == 1) {
          $max_i = $i;
          $max = $value;
        }
        return $max_i;
      }
    };

    $m = $this->rows;
    $n = $this->cols;
    $h = $k = 0;
    while($h < $m && $k < $n) {
      $i_max = $argmax($h, $m, $this, $k);
      if($this($i_max, $k) == 0) {
        ++$k;
      } else {
        $this->rowSwap($h, $i_max);
        for($i = $h + 1; $i < $m; ++$i) {
          $f = bcdiv($this($i, $k), $this($h, $k));
          $this($i, $k, 0);
          for($j = $k + 1; $j < $n; ++$j) {
            $val = bcsub($this($i, $j), bcmul($this($h, $j), $f));
            $this($i, $j, $val);
          }
        }
        ++$h;
        ++$k;
      }
    }

    bcscale($old_scale);
  }

  /**
   * Use Gaussian elimination to solve the equations with given RHS
   */
  public function gaussian_solve(Matrix $rhs, $bcscale = 50)
  {
    $a = $this->augment($rhs);
    $a->gaussian($bcscale);
    return $this->solve($a, $bcscale);
  }

  /**
   * Creates a new matrix with $this on left and $rhs on right
   */
  public function augment(Matrix $rhs)
  {
    $m = $this->rows;
    $n = $this->cols + $rhs->cols;
    $aug = new Matrix($m, $n);

    $c = 0;
    for($i = 0; $i < $m; ++$i) {
      for($j = 0; $j < $this->cols; ++$j)
        $aug->data[$c++] = $this($i, $j);
      for($j = 0; $j < $rhs->cols; ++$j)
        $aug->data[$c++] = $rhs($i, $j);
    }
    return $aug;
  }

  /**
   * Solves simultaneous equations using Gaussian elimination
   */
  public function solve(Matrix $a, $bcscale)
  {
    $result = new Matrix(1, $a->rows);
    $old_scale = bcscale($bcscale);

    // back substitution
    $m = $a->rows;
    $n = $a->cols;
    for($i = $m - 1; $i >= 0; --$i) {
      for($j = $n - 2; $j > $i; --$j) {
        $value = bcsub($a($i, $n - 1),
          bcmul($a($i, $j), $result(0, $j)));
        $a($i, $n - 1, $value);
        $a($i, $j, 0);
      }
      $d = $a($i, $i);
      if($d == 0)
        return null;
      $value = bcdiv($a($i, $n - 1), $a($i, $i));
      $result(0, $i, $value);
    }

    bcscale($old_scale);
    return $result;
  }

  /**
   * Swaps two rows
   */
  public function rowSwap($r1, $r2)
  {
    for($i = 0; $i < $this->cols; ++$i) {
      $c = $this($r1, $i);
      $this($r1, $i, $this($r2, $i));
      $this($r2, $i, $c);
    }
  }

  /**
   * Output as string for debugging
   */
  public function __toString()
  {
    $str = '';
    $m = 0;
    foreach($this->data as $v) {
      $m1 = abs($v);
      if($m1 > $m)
        $m = $m1;
    }

    $digits = max(9,(int)log($m, 10) + 6);
    for($r = 0; $r < $this->rows; ++$r) {
      $str .= "\t";
      $r_offset = $r * $this->cols;
      for($c = 0; $c < $this->cols; ++$c) {
        $str .= sprintf("  %{$digits}.4f", $this->data[$r_offset + $c]);
      }
      $str .= "\n";
    }
    return $str;
  }

  /**
   * Returns the data array
   */
  public function asArray()
  {
    return $this->data;
  }
}
