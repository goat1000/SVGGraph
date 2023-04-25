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
 * Class for calculating a curved best-fit line
 */
class BestFitCurve {

  protected $graph;
  protected $points;
  protected $line;
  protected $projection;
  protected $types;

  public function __construct(&$graph, $points, $types = null)
  {
    $this->graph =& $graph;
    $this->line = new PathData;
    $this->projection = new PathData;
    $this->points = $points;
    $this->types = $types === null || is_array($types) ? $types : [$types];
  }

  /**
   * Calculates the line and projection
   */
  public function calculate(BoundingBox $area, $limit_start, $limit_end,
    $project_start, $project_end)
  {
    // can't draw a line through fewer than 2 points
    $count = count($this->points);
    if($count < 2)
      return false;

    $old_scale = bcscale(50);
    $b = [];
    $y = new Matrix($count, 1);
    foreach($this->points as $p)
      $b[] = $p->y;
    $y->load($b);

    $supported_types = ['straight', 'quadratic', 'cubic', 'quartic', 'quintic'];

    // choose which functions to fit
    if($this->types !== null) {
      $types = [];
      foreach($this->types as $type) {
        if(!in_array($type, $supported_types))
          throw new \Exception("Unknown curve type '{$type}'");
        $types[] = $type;
      }
    } else {
      $types = ['quintic'];
      switch($count)
      {
      case 2 : $types = ['straight'];
        break;
      case 3 : $types = ['quadratic'];
        break;
      case 4 : $types = ['cubic'];
        break;
      case 5 : $types = ['quartic'];
      }
    }

    // fit the functions, measure the error
    $results = [];
    $errors = [];
    foreach($types as $t) {
      $v = $this->vandermonde($t);
      $result = $this->solve($v, $y);
      if($result !== null) {
        $errors[$t] = $this->error($v, $result);
        $results[$t] = $result;
      }
    }

    if(!empty($errors)) {

      // sort by error, best first
      uasort($errors, 'bccomp');
      $best = null;
      foreach($errors as $k => $v) {
        $best = $k;
        break;
      }

      $r = $results[$best];
      $c = $r->asArray();

      // plot the function
      $fn = new Algebraic($best);
      $fn->setCoefficients($c);
      $this->buildPaths($fn, $area, $limit_start, $limit_end,
        $project_start, $project_end);
    }
    bcscale($old_scale);
  }

  /**
   * Calculates the error
   */
  protected function error(Matrix $v, Matrix $r)
  {
    $old_scale = bcscale(50);
    $tr = $r->transpose();
    $vr = $v->multiply($tr);
    $i = 0;
    $err = "0";
    foreach($this->points as $p) {
      $diff = bcsub($vr($i, 0), $p->y);
      if(bccomp($diff, "0") == -1)
        $err = bcsub($err, $diff);
      else
        $err = bcadd($err, $diff);
      ++$i;
    }
    bcscale($old_scale);
    return $err;
  }

  /**
   * Solves the normal equation
   */
  protected function solve(Matrix $v, Matrix $y)
  {
    $v_t = $v->transpose();
    $v_t_v = $v_t->multiply($v);
    $v_t_y = $v_t->multiply($y);
    return $v_t_v->gaussian_solve($v_t_y);
  }

  /**
   * Returns the Vandermonde matrix for the curve type
   */
  protected function vandermonde($type)
  {
    $old_scale = bcscale(50);

    // find size of matrix
    $a = new Algebraic($type);
    $test = $a->vandermonde(1);
    $m = count($this->points);
    $n = count($test) + 1;
    $v = new Matrix($m, $n);

    $i = 0;
    foreach($this->points as $p)
    {
      $v($i, 0, 1);
      $bcx = sprintf("%20.20F", $p->x);
      $cols = $a->vandermonde($bcx);
      foreach($cols as $k => $value)
        $v($i, $k + 1, $value);
      ++$i;
    }
    bcscale($old_scale);
    return $v;
  }

  /**
   * Builds the line and projection paths.
   * For vertical lines, $slope = null and $y_int = $x
   */
  protected function buildPaths(&$fn, $area, $limit_start, $limit_end,
    $project_start, $project_end)
  {

    // initialize min and max points of line
    $x_min = $limit_start === null ? 0 : max($limit_start, 0);
    $x_max = $limit_end === null ? $area->width() : min($limit_end, $area->width());
    $y_min = 0;
    $y_max = $area->height();
    $line = new PathData;
    $projection = new PathData;

    $step = 1;
    if($project_start)
      $this->buildPath($projection, $fn, 0, $x_min, $y_min, $y_max, $step, $area);
    $this->buildPath($line, $fn, $x_min, $x_max, $y_min, $y_max, $step, $area);
    if($project_end)
      $this->buildPath($projection, $fn, $x_max, $area->width(), $y_min, $y_max, $step, $area);

    $this->projection = $projection;
    $this->line = $line;
    return true;
  }

  /**
   * Builds a single path section between $x1 and $x2
   */
  private function buildPath(&$path, &$fn, $x1, $x2, $y_min, $y_max, $step, $area)
  {
    $cmd = 'M';
    for($x = $x1; $x <= $x2; $x += $step) {
      $y = $fn($x);
      if($y < $y_min || $y > $y_max) {
        $cmd = 'M';
        continue;
      }
      $path->add($cmd, $area->x1 + $x, $area->y2 - $y);
      switch($cmd) {
      case 'M' : $cmd = 'L'; break;
      case 'L' : $cmd = ''; break;
      }
    }
  }

  /**
   * Returns the best-fit line as PathData
   */
  public function getLine()
  {
    return $this->line;
  }

  /**
   * Returns the projection line(s) as PathData
   */
  public function getProjection()
  {
    return $this->projection;
  }
}

