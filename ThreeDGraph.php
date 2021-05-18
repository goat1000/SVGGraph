<?php
/**
 * Copyright (C) 2009-2021 Graham Breach
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

abstract class ThreeDGraph extends GridGraph {

  // Number of data ranges
  protected $depth = 1;
  protected $depth_unit = 1;

  /**
   * Converts x,y,z coordinates into flat x,y
   */
  protected function project($x, $y, $z)
  {
    $a = deg2rad($this->project_angle);
    $xp = $z * cos($a);
    $yp = $z * sin($a);
    return [$x + $xp, $y - $yp];
  }

  /**
   * Adjust axes for block spacing, setting the depth unit
   */
  protected function adjustAxes(&$x_len, &$y_len)
  {
    // make sure project_angle is in range
    if($this->project_angle < 1)
      $this->project_angle = 1;
    elseif($this->project_angle > 90)
      $this->project_angle = 90;

    $ends = $this->getAxisEnds();
    $bars = $ends['k_max'][0] - $ends['k_min'][0] + 1;
    $a = deg2rad($this->project_angle);

    $depth = $this->depth;
    $u = $x_len / ($bars + $depth * cos($a));
    $d = $u * $depth * sin($a);
    if($d > $y_len) {
      // doesn't fit - use 1/2 y length
      $d = $y_len / 2;
      $u = $d / $depth * sin($a);
    }
    $c = $u * $depth * cos($a);
    $x_len -= $c;
    $y_len -= $d;
    $this->depth_unit = $u;
    return [$c, $d];
  }

  /**
   * Draws the grid behind the bar / line graph
   */
  protected function grid()
  {
    $this->calcAxes();
    $this->calcGrid();
    if(!$this->show_grid || (!$this->show_grid_h && !$this->show_grid_v))
      return '';

    // move to depth
    $z = $this->depth * $this->depth_unit;
    list($xd,$yd) = $this->project(0, 0, $z);
    $x_w = new Number($this->g_width);

    // convert to Number now - more efficient
    $minus_x_w = new Number(-$this->g_width);
    $y_h = new Number($this->g_height);
    $minus_y_h = new Number(-$this->g_height);
    $xleft = new Number($this->pad_left);
    $ybottom = new Number($this->height - $this->pad_bottom);
    $minus_xd = new Number(-$xd);
    $minus_yd = new Number(-$yd);
    $xd = new Number($xd);
    $yd = new Number($yd);

    $back = $subpath = $path = '';
    $back_colour = new Colour($this, $this->getOption('grid_back_colour'));
    if(!$back_colour->isNone()) {
      $dpath = new PathData('M', $xleft, $ybottom, 'v', $minus_y_h, 'l', $xd, $yd);
      $dpath->add('h', $x_w, 'v', $y_h, 'l', $minus_xd, $minus_yd, 'z');
      $bpath = [
        'd' => $dpath,
        'fill' => $back_colour
      ];
      if($this->grid_back_opacity != 1)
        $bpath['fill-opacity'] = $this->grid_back_opacity;
      $back = $this->element('path', $bpath);
    }
    if($this->grid_back_stripe) {
      // use array of colours if available, otherwise stripe a single colour
      $colours = is_array($this->grid_back_stripe_colour) ?
        $this->grid_back_stripe_colour :
        [null, $this->grid_back_stripe_colour];
      $pathdata = '';
      $c = 0;
      $p1 = null;
      $num_colours = count($colours);
      $points = $this->getGridPointsY($this->main_y_axis);
      $first = array_shift($points);
      $last_pos = $first->position;
      foreach($points as $y) {
        $y = $y->position;
        $cc = $colours[$c % $num_colours];
        if($cc !== null) {
          $y1 = $last_pos - $y;
          $dpath = new PathData('M', $xleft, $y, 'l', $xd, $yd);
          $dpath->add('h', $x_w, 'v', $y1, 'h', $minus_x_w);
          $dpath->add('l', $minus_xd, $minus_yd, 'z');
          $bpath = [
            'fill' => new Colour($this, $cc),
            'd' => $dpath,
          ];
          if($this->grid_back_stripe_opacity != 1)
            $bpath['fill-opacity'] = $this->grid_back_stripe_opacity;
          $back .= $this->element('path', $bpath);
        } else {
          $p1 = $y;
        }
        $last_pos = $y;
        ++$c;
      }
    }
    if($this->show_grid_subdivisions) {
      $subpath_h = new PathData;
      $subpath_v = new PathData;
      if($this->show_grid_h) {
        $subdivs = $this->getSubDivsY($this->main_y_axis);
        foreach($subdivs as $y)
          $subpath_v->add('M', $xleft, $y->position, 'l', $xd, $yd,
            'l', $x_w, 0);
      }
      if($this->show_grid_v) {
        $subdivs = $this->getSubDivsX(0);
        foreach($subdivs as $x)
          $subpath_h->add('M', $x->position, $ybottom, 'l', $xd, $yd,
            'l', 0, $minus_y_h);
      }
      if(!($subpath_h->isEmpty() && $subpath_v->isEmpty())) {
        $colour_h = $this->getOption('grid_subdivision_colour_h',
          'grid_subdivision_colour', 'grid_colour_h', 'grid_colour');
        $colour_v = $this->getOption('grid_subdivision_colour_v',
          'grid_subdivision_colour', 'grid_colour_v', 'grid_colour');
        $dash_h = $this->getOption('grid_subdivision_dash_h',
          'grid_subdivision_dash', 'grid_dash_h', 'grid_dash');
        $dash_v = $this->getOption('grid_subdivision_dash_v',
          'grid_subdivision_dash', 'grid_dash_v', 'grid_dash');
        $width_h = $this->getOption('grid_subdivision_stroke_width_h',
          'grid_subdivision_stroke_width', 'grid_stroke_width_h',
          'grid_stroke_width');
        $width_v = $this->getOption('grid_subdivision_stroke_width_v',
          'grid_subdivision_stroke_width', 'grid_stroke_width_v',
          'grid_stroke_width');

        if($dash_h == $dash_v && $colour_h == $colour_v && $width_h == $width_v) {
          $subpath_h->add($subpath_v);
          $subpath = $this->gridLines($subpath_h, $colour_h, $dash_h, $width_h,
            ['fill' => 'none']);
        } else {
          $subpath = $this->gridLines($subpath_h, $colour_h, $dash_h,$width_h,
            ['fill' => 'none']) .
            $this->gridLines($subpath_v, $colour_v, $dash_v, $width_v,
              ['fill' => 'none']);
        }
      }
    }

    // start with axis lines
    $path = new PathData('M', $xleft, $ybottom, 'l', $x_w, 0);
    $path->add('M', $xleft, $ybottom, 'l', 0, $minus_y_h);
    $path_v = new PathData;
    $path_h = new PathData;
    if($this->show_grid_h) {
      $points = $this->getGridPointsY($this->main_y_axis);
      foreach($points as $y)
        $path_v->add('M', $xleft, $y->position, 'l', $xd, $yd, 'l', $x_w, 0);
    }
    if($this->show_grid_v) {
      $points = $this->getGridPointsX(0);
      foreach($points as $x)
        $path_h->add('M', $x->position, $ybottom, 'l', $xd, $yd, 'l', 0, $minus_y_h);
    }

    $colour_h = $this->getOption('grid_colour_h', 'grid_colour');
    $colour_v = $this->getOption('grid_colour_v', 'grid_colour');
    $dash_h = $this->getOption('grid_dash_h', 'grid_dash');
    $dash_v = $this->getOption('grid_dash_v', 'grid_dash');
    $width_h = $this->getOption('grid_stroke_width_h', 'grid_stroke_width');
    $width_v = $this->getOption('grid_stroke_width_v', 'grid_stroke_width');

    if($dash_h == $dash_v && $colour_h == $colour_v && $width_h == $width_v) {
      $path_h->add($path_v);
      $path = $this->gridLines($path_h, $colour_h, $dash_h, $width_h,
        ['fill' => 'none']);
    } else {
      $path = $this->gridLines($path_h, $colour_h, $dash_h, $width_h,
        ['fill' => 'none']) .
        $this->gridLines($path_v, $colour_v, $dash_v, $width_v,
          ['fill' => 'none']);
    }

    return $back . $subpath . $path;
  }

  /**
   * clamps a value to the grid boundaries
   */
  protected function clampVertical($val)
  {
    return max($this->height - $this->pad_bottom - $this->g_height,
      min($this->height - $this->pad_bottom, $val));
  }

  protected function clampHorizontal($val)
  {
    return max($this->width - $this->pad_right - $this->g_width,
      min($this->width - $this->pad_right, $val));
  }

  /**
   * Returns the path for a guideline, and sets dimensions of the straight bit
   */
  public function guidelinePathBelow($axis, $value, &$x, &$y, &$w, &$h,
    $reverse_length)
  {
    $coords = new Coords($this);

    $grid_value = 'g' . (is_numeric($value) ? new Number($value) : $value);
    if($axis == 'x') {
      $y = $coords->transform('gt', 'y');
      $x = $coords->transform($grid_value, 'x', null);
      if($x === null)
        return '';

      if(is_string($h) || $h > 0) {
        $h = $coords->transform($h, 'y');
      } else {
        $h = $coords->transform('gh', 'y');
      }
      if(!$reverse_length)
        $y = $coords->transform('gb', 'y') - $h;

    } else {
      $x = $coords->transform('gl', 'x');
      $y = $coords->transform($grid_value, 'y', null);
      if($y === null)
        return '';

      if(is_string($w) || $w > 0) {
        $w = $coords->transform($w, 'x');
      } else {
        $w = $coords->transform('gw', 'x');
      }
      if($reverse_length)
        $x = $coords->transform('gr', 'x') - $w;
      $h = 0;
    }

    // get the depth section of the path
    $z = $this->depth * $this->depth_unit;
    list($xd, $yd) = $this->project(0, 0, $z);
    $x1 = $x;
    $y1 = $y + $h;
    $x = $x + $xd;
    $y = $y + $yd;

    // for reverse lengths the line doesn't meet the axis
    $path = new PathData('M', $x, $y, 'l', $w, $h);
    if(!$reverse_length)
      $path->add('M', $x1, $y1, 'l', $xd, $yd);
    return $path;
  }

  /**
   * Returns TRUE if the item is visible on the graph
   */
  public function isVisible($item, $dataset = 0)
  {
    // 0 values are visible, NULLs are not
    return $item->value !== null;
  }
}

