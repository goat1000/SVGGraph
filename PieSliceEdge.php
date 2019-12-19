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
 * The PieSliceEdge class calculates and draws the 3D slice edges
 */
class PieSliceEdge {

  public $x;
  public $y;
  public $slice;

  // types: 0 => start, 1 => end, 2 => curve,
  // 3 => second curve (if it exists), -1 = no edge
  protected $type;
  protected $a1;
  protected $a2;

  /**
   * $slice is the slice details array
   * $s_angle is the start angle in radians
   */
  public function __construct(&$graph, $type, $slice, $s_angle)
  {
    $this->type = $type;
    $this->slice = $slice;

    $start_angle = $slice['angle_start'] + $s_angle;
    $end_angle = $slice['angle_end'] + $s_angle;

    if(isset($slice['single_slice']) && $slice['single_slice'] &&
      !is_numeric($graph->end_angle)) {
      // if end_angle is not set, then single_slice is full pie
      $start_angle = 0.0;
      $end_angle = M_PI;
    } elseif($graph->reverse) {
      // apply reverse now to save thinking about it later
      $s = M_PI * 4.0 - $end_angle;
      $e = M_PI * 4.0 - $start_angle;
      $start_angle = $s;
      $end_angle = $e;
    }

    $this->a1 = fmod($start_angle, M_PI * 2.0);
    $this->a2 = fmod($end_angle, M_PI * 2.0);
    if($this->a2 < $this->a1)
      $this->a2 += M_PI * 2.0;

    // truncate curves to visible area
    if($type == 2) {
      if($this->a1 < M_PI && $this->a2 > M_PI)
        $this->a2 = M_PI;
      elseif($this->a1 > M_PI && $this->a2 > M_PI * 2.0)
        $this->a1 = M_PI * 2.0;
    }
    if($type == 3) {
      // type 3 edges are for pie slices that show at both sides
      if($this->a1 < M_PI && $this->a2 > M_PI * 2.0)
        $this->a1 = M_PI * 2.0;
      else
        $this->type = -1;
    }

    if($type == 0 || $type == 1) {
      $angle = $type == 1 ? $this->a2 : $this->a1;
      $this->x = 2000.0 * cos($angle);
      $this->y = 2000.0 * sin($angle);
    } else {
      // if the edge crosses the bottom use full distance
      if(($this->a1 < M_PI * 0.5 && $this->a2 > M_PI * 0.5) ||
        ($this->a2 > M_PI * 2.5)) {
        $this->x = 0;
        $this->y = 2000.0;
      } else {
        $s1 = 2000.0 * sin($this->a1);
        $s2 = 2000.0 * sin($this->a2);
        if($s1 > $s2) {
          $this->y = $s1;
          $this->x = 2000.0 * cos($this->a1);
        } else {
          $this->y = $s2;
          $this->x = 2000.0 * cos($this->a2);
        }
      }
    }
  }

  /**
   * Returns TRUE if the edge faces forwards
   */
  public function visible()
  {
    // type -1 is for non-existent edges
    if($this->type == -1)
      return false;

    // the flat edges are visible left or right
    if($this->type == 0) {
      // start on right not visible
      if($this->a1 < M_PI * 0.5 || $this->a1 > M_PI * 1.5)
        return false;
      return true;
    }

    $a2 = fmod($this->a2, M_PI * 2.0);
    if($this->type == 1) {
      // end on left not visible
      if($a2 > M_PI * 0.5 && $a2 < M_PI * 1.5)
        return false;
      return true;
    }

    // if both ends are at top and slice angle < 180, not visible
    if($this->a1 >= M_PI && $this->a2 <= M_PI * 2.0 &&
      $this->a2 - $this->a1 < M_PI * 2.0)
      return false;
    return true;
  }

  /**
   * Returns TRUE if this is a curved edge
   */
  public function curve()
  {
    return $this->type == 2 || $this->type == 3;
  }

  /**
   * Draws the edge
   */
  public function draw(&$graph, $x_centre, $y_centre, $depth, $attr = null)
  {
    $attr = ($attr === null ? $this->slice['attr'] :
      array_merge($this->slice['attr'], $attr));
    $attr['d'] = $this->getPath($x_centre, $y_centre, $depth);
    return $graph->element('path', $attr);
  }

  /**
   * Returns the edge as a clipPath element
   */
  public function getClipPath(&$graph, $x_centre, $y_centre, $depth, $clip_id)
  {
    $attr = ['id' => $clip_id];
    $path = ['d' => $this->getPath($x_centre, $y_centre, $depth)];

    return $graph->element('clipPath', $attr, null,
      $graph->element('path', $path));
  }

  /**
   * Returns the correct path
   */
  protected function getPath($x_centre, $y_centre, $depth)
  {
    if($this->type == 0 || $this->type == 1) {
      $path = $this->getFlatPath($this->type == 1 ? $this->a2 : $this->a1,
        $x_centre, $y_centre, $depth);
    } else {
      $path = $this->getCurvedPath($x_centre, $y_centre, $depth);
    }
    return $path;
  }

  /**
   * Returns the path for a flat edge
   */
  protected function getFlatPath($angle, $x_centre, $y_centre, $depth)
  {
    $x1 = $x_centre + $this->slice['radius_x'] * cos($angle);
    $y1 = $y_centre + $this->slice['radius_y'] * sin($angle) + $depth;
    return new PathData('M', $x_centre, $y_centre, 'v', $depth, 'L', $x1, $y1,
      'v', -$depth, 'z');
  }

  /**
   * Returns the path for the curved edge
   */
  protected function getCurvedPath($x_centre, $y_centre, $depth)
  {
    $rx = $this->slice['radius_x'];
    $ry = $this->slice['radius_y'];
    $x1 = $x_centre + $rx * cos($this->a1);
    $y1 = $y_centre + $ry * sin($this->a1);
    $x2 = $x_centre + $rx * cos($this->a2);
    $y2 = $y_centre + $ry * sin($this->a2);
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

