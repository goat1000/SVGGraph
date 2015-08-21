<?php
/**
 * Copyright (C) 2010-2015 Graham Breach
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

require_once 'SVGGraphPieGraph.php';

class Pie3DGraph extends PieGraph {

  protected function Draw()
  {
    // modify pad_bottom to make PieGraph do the hard work
    $pb = $this->pad_bottom;
    $space = $this->height - $this->pad_top - $this->pad_bottom;
    if($space < $this->depth)
      $this->depth = $space / 2;
    $this->pad_bottom += $this->depth;
    $this->Calc();
    $this->pad_bottom = $pb;
    return PieGraph::Draw();
  }

  /**
   * Override the parent to draw 3D slice
   */
  protected function GetSlice($item, $angle_start, $angle_end, $radius_x,
    $radius_y, &$attr, $single_slice)
  {
    $x_start = $y_start = $x_end = $y_end = 0;
    $angle_start += $this->s_angle;
    $angle_end += $this->s_angle;
    $this->CalcSlice($angle_start, $angle_end, $radius_x, $radius_y, 
      $x_start, $y_start, $x_end, $y_end);

    $outer = $angle_end - $angle_start > M_PI ? 1 : 0;
    $sweep = $this->reverse ? 0 : 1;
    $side_start = $this->reverse ? M_PI : M_PI * 2;
    $side_end = $this->reverse ? M_PI * 2 : M_PI;

    $path = '';
    $angle_start_lower = $this->LowerHalf($angle_start);
    $angle_end_lower = $this->LowerHalf($angle_end);

    // cope with the lower half filled exactly
    if($single_slice ||
      ($this->reverse && $angle_start == M_PI && $angle_end == M_PI * 2) ||
      (!$this->reverse && $angle_start == 0 && $angle_end == M_PI)) {
      $angle_end_lower = $angle_start_lower = true;
    }
    if($angle_start_lower || $angle_end_lower || $outer) {
      if($angle_start_lower && $angle_end_lower && $outer) {
        // if this is a big slice with both sides at bottom, need 2 edges
        $path .= $this->GetEdge($angle_start, $side_end, $radius_x, $radius_y);
        $path .= $this->GetEdge($side_start, $angle_end, $radius_x, $radius_y);
      } else {
        // if an edge is in the top half, need to truncate to x-radius
        $angle_start_trunc = $angle_start_lower ? $angle_start : $side_start;
        $angle_end_trunc = $angle_end_lower ? $angle_end : $side_end;
        $path .= $this->GetEdge($angle_start_trunc, $angle_end_trunc, $radius_x, $radius_y);
      }
    }

    if($single_slice) {
      $attr_path = array('d' => $path);
      $attr_ellipse = array(
        'cx' => $this->x_centre, 'cy' => $this->y_centre,
        'rx' => $radius_x, 'ry' => $radius_y
      );
      return $this->Element('g', $attr, NULL, 
        $this->Element('path', $attr_path) .
        $this->Element('ellipse', $attr_ellipse));
    } else {
      $outer = ($angle_end - $angle_start > M_PI ? 1 : 0);
      $sweep = ($this->reverse ? 0 : 1);
      $attr['d'] = $path . "M{$this->x_centre},{$this->y_centre} " .
        "L$x_start,$y_start A{$radius_x} {$radius_y} 0 " .
        "$outer,$sweep $x_end,$y_end z";
      return $this->Element('path', $attr);
    }
  }

  /**
   * Returns the path for an edge
   */
  protected function GetEdge($angle_start, $angle_end, $radius_x, $radius_y,
    $double_curve = false)
  {
    $x_start = $y_start = $x_end = $y_end = 0;
    $this->CalcSlice($angle_start, $angle_end, $radius_x, $radius_y,
      $x_start, $y_start, $x_end, $y_end);
    $y_end_depth = $y_end + $this->depth;

    $outer = 0; // edge is never > PI
    $sweep = $this->reverse ? 0 : 1;

    $path = "M$x_start,$y_start v{$this->depth} " .
      "A{$radius_x} {$radius_y} 0 " .
      "$outer,$sweep $x_end,$y_end_depth v-{$this->depth} ";
    if($double_curve) {
      $sweep = $sweep ? 0 : 1;
      $path .= "A{$radius_x} {$radius_y} 0 " .
        "$outer,$sweep $x_start,$y_start ";
    }
    return $path;
  }

  /**
   * Returns TRUE if the angle is in the lower half of the pie
   */
  protected function LowerHalf($angle)
  {
    $angle = fmod($angle, M_PI * 2);
    return ($this->reverse && $angle > M_PI && $angle < M_PI * 2) ||
      (!$this->reverse && $angle < M_PI && $angle > 0);
  }

  /**
   * Overlays the gradient on the pie sides
   */
  protected function PieExtras()
  {
    $overlay = '';
    if(is_array($this->depth_shade_gradient)) {
      $gradient_id = $this->AddGradient($this->depth_shade_gradient);
      $start = $this->reverse ? M_PI : M_PI * 2;
      $end = $this->reverse ? M_PI * 2 : M_PI;
      $bottom = array(
        'd' => $this->GetEdge($start, $end, $this->radius_x, $this->radius_y, true),
        'fill' => "url(#{$gradient_id})"
      );
      $overlay = $this->Element('path', $bottom);
    }

    return $overlay;
  }
}

