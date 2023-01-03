<?php
/**
 * Copyright (C) 2021-2022 Graham Breach
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
 * The DonutSliceEdge class calculates and draws the 3D slice edges
 */
class DonutSliceEdge extends PieSliceEdge {

  protected $ratio = 1.0;
  protected $outer_a = 0;
  protected $inner_a = 0;

  /**
   * $slice is the slice details array
   * $s_angle is the start angle in radians
   */
  public function __construct(&$graph, $type, $slice, $s_angle)
  {
    // types: 0 => start flat, 1 => end flat, 2, 3, 4, 5  => curves, -1 => no edge
    $this->type = -1;
    $this->slice = $slice;
    $tau = M_PI * 2.0;

    $start_angle = $slice['angle_start'] + $s_angle;
    $end_angle = $slice['angle_end'] + $s_angle;
    $ratio = min(0.99, max(0.01, $graph->getOption('inner_radius')));
    list($outer_a, $inner_a) = $graph->getSliceGap($end_angle - $start_angle, $ratio);
    $this->outer_a = $outer_a;
    $this->inner_a = $inner_a;

    if(isset($slice['single_slice']) && $slice['single_slice'] &&
      !is_numeric($graph->end_angle)) {

      // full pie, draw full bottom and inner edges only
      switch($type)
      {
      case 2:
        $start_angle = 0.0;
        $end_angle = M_PI;
        break;
      case 4:
        $start_angle = M_PI;
        $end_angle = $tau;
        break;
      default:
        return;
      }
    } elseif($graph->getOption('reverse')) {
      // apply reverse now to save thinking about it later
      $s = M_PI * 4.0 - $end_angle;
      $e = M_PI * 4.0 - $start_angle;
      $start_angle = $s;
      $end_angle = $e;
    }

    $this->a1 = fmod($start_angle, $tau);
    $this->a2 = fmod($end_angle, $tau);
    if($this->a2 < $this->a1)
      $this->a2 += $tau;

    switch($type) {
    case 0:
      // flat edge at a1
      $this->a2 = $this->a1;
      $this->ratio = $ratio;
      break;
    case 1:
      // flat edge at a2
      $this->a1 = $this->a2;
      $this->ratio = $ratio;
      $this->outer_a = -$outer_a;
      $this->inner_a = -$inner_a;
      break;
    case 2:
      // bottom edge
      if($this->a1 > M_PI && $this->a2 < $tau)
        return;
      // truncate curves to visible area
      if($this->a1 <= M_PI && $this->a2 >= M_PI)
        $this->a2 = M_PI;
      elseif($this->a1 > M_PI && $this->a2 > $tau)
        $this->a1 = $tau;
      if($this->a2 > M_PI * 3.0)
        $this->a2 = M_PI * 3.0;
      break;
    case 3:
      // type 3 edges are where the slice starts bottom, goes through top and ends at bottom
      if($this->a2 < $tau || $this->a2 > M_PI * 3.0 || $this->a1 >= M_PI)
        return;

      $this->a1 = $tau;
      break;
    case 4:
      // slices passing through top
      if($this->a2 <= M_PI)
        return;

      if($this->a1 < M_PI)
        $this->a1 = M_PI;
      if($this->a2 > $tau)
        $this->a2 = $tau;
      $this->ratio = $ratio;
      break;
    case 5:
      // slice starts at top, passes through bottom and ends at top
      if($this->a2 < M_PI * 3.0)
        return;

      $this->a1 = M_PI * 3.0;
      $this->ratio = $ratio;
      break;
    }

    // ignore tiny curves as floating point artifacts
    if($type > 1 && abs($this->a1 - $this->a2) < 0.0001)
      return;

    $this->setupSort();
    $this->type = $type;
  }

  /**
   * Returns the number of edge types this class supports
   */
  protected static function getEdgeTypes()
  {
    return 5;
  }

  /**
   * Returns TRUE when the edge is visible
   */
  public function visible()
  {
    switch($this->type)
    {
    case -1:
      return false; // type -1 is for non-existent edges
    case 0:
      // start on right not visible
      if($this->a1 < M_PI * 0.5 || $this->a1 > M_PI * 1.5)
        return false;
      break;
    case 1:
      // end on left not visible
      $a2 = fmod($this->a2, M_PI * 2.0);
      if($a2 > M_PI * 0.5 && $a2 < M_PI * 1.5)
        return false;
      break;
    }

    // curves always visible on donut graph
    return true;
  }

  /**
   * Returns TRUE when this is an inner edge
   */
  public function inner()
  {
    return $this->type > 3;
  }

  /**
   * Returns the ratio of inner to outer
   */
  public function getInnerRatio()
  {
    return $this->ratio;
  }

  /**
   * Returns the path for a flat edge
   */
  protected function getFlatPath($angle, $x_centre, $y_centre, $depth)
  {
    $rx1 = $this->slice['radius_x'] * cos($angle + $this->outer_a);
    $ry1 = $this->slice['radius_y'] * sin($angle + $this->outer_a);
    $rx2 = $this->slice['radius_x'] * $this->ratio * cos($angle + $this->inner_a);
    $ry2 = $this->slice['radius_y'] * $this->ratio * sin($angle + $this->inner_a);
    $x1 = $x_centre + $rx1;
    $y1 = $y_centre + $ry1;
    $x2 = $x_centre + $rx2;
    $y2 = $y_centre + $ry2;
    return new PathData('M', $x2, $y2, 'v', $depth, 'L', $x1, $y1 + $depth,
      'v', -$depth, 'z');
  }

  /**
   * Returns the path for the curved edge
   */
  protected function getCurvedPath($x_centre, $y_centre, $depth)
  {
    $a = $this->ratio < 1.0 ? $this->inner_a : $this->outer_a;
    $rx = $this->slice['radius_x'] * $this->ratio;
    $ry = $this->slice['radius_y'] * $this->ratio;
    $x1 = $x_centre + $rx * cos($this->a1 + $a);
    $y1 = $y_centre + $ry * sin($this->a1 + $a);
    $x2 = $x_centre + $rx * cos($this->a2 - $a);
    $y2 = $y_centre + $ry * sin($this->a2 - $a);
    $y2d = $y2 + $depth;

    $outer = 0; // edge is never > PI
    $sweep = 1;

    $path = new PathData('M', $x1, $y1, 'v', $depth, 'A', $rx, $ry, 0,
      $outer, $sweep, $x2, $y2d, 'v', -$depth);
    $sweep = $sweep ? 0 : 1;
    $path->add('A', $rx, $ry, 0, $outer, $sweep, $x1, $y1);
    return $path;
  }
}

