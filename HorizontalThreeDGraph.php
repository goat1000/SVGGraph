<?php
/**
 * Copyright (C) 2022 Graham Breach
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

abstract class HorizontalThreeDGraph extends HorizontalGridGraph {

  use ThreeDTrait;

  /**
   * Adjust axes for block spacing, setting the depth unit
   */
  protected function adjustAxes(&$x_len, &$y_len)
  {
    // make sure project_angle is in range
    $this->project_angle = min(89, max(1, $this->getOption('project_angle', 30)));

    $ends = $this->getAxisEnds();
    $bars = $ends['k_max'][0] - $ends['k_min'][0] + 1;
    $a = deg2rad($this->project_angle);

    $depth = $this->depth;
    $u = $y_len / ($bars + $depth * cos($a));
    $c = $u * $depth * cos($a);
    if($c > $x_len) {
      // doesn't fit - use 1/2 length
      $c = $x_len / 2;
      $u = $d / $depth * cos($a);
    }
    $d = $u * $depth * sin($a);
    $x_len -= $c;
    $y_len -= $d;
    $this->depth_unit = $y_len / $bars;
    return [$c, $d];
  }

  /**
   * Returns the (vertical) grid stripes as a string
   */
  protected function getGridStripes()
  {
    if(!$this->getOption('grid_back_stripe'))
      return '';

    $z = $this->depth * $this->depth_unit;
    list($xd,$yd) = $this->project(0, 0, $z);
    $y_h = new Number($this->g_height);
    $minus_y_h = new Number(-$this->g_height);
    $ybottom = new Number($this->height - $this->pad_bottom);
    $minus_xd = new Number(-$xd);
    $minus_yd = new Number(-$yd);
    $xd = new Number($xd);
    $yd = new Number($yd);

    // use array of colours if available, otherwise stripe a single colour
    $colours = $this->getOption('grid_back_stripe_colour');
    if(!is_array($colours))
      $colours = [null, $colours];
    $opacity = $this->getOption('grid_back_stripe_opacity');

    $pathdata = '';
    $c = 0;
    $p1 = null;
    $num_colours = count($colours);
    $points = $this->getGridPointsX($this->main_x_axis);
    $first = array_shift($points);
    $last_pos = $first->position;
    $stripes = '';
    foreach($points as $x) {
      $x = $x->position;
      $cc = $colours[$c % $num_colours];
      if($cc !== null) {
        $x1 = $last_pos - $x;
        $dpath = new PathData('M', $x, $ybottom,
          'l', $xd, $yd,
          'v', $minus_y_h,
          'h', $x1,
          'v', $y_h,
          'l', $minus_xd, $minus_yd, 'z');
        $bpath = [
          'fill' => new Colour($this, $cc),
          'd' => $dpath,
        ];
        if($opacity != 1)
          $bpath['fill-opacity'] = $opacity;
        $stripes .= $this->element('path', $bpath);
      } else {
        $p1 = $x;
      }
      $last_pos = $x;
      ++$c;
    }
    return $stripes;
  }
}
