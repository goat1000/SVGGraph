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

class Bar3D {

  protected $graph;
  protected $overlay_top;
  protected $overlay_side;
  protected $overlay_front;
  protected $overlay_top_colour;
  protected $overlay_side_colour;
  protected $overlay_front_colour;
  protected $skew_top;
  protected $skew_side;
  protected $angle;
  protected $depth;
  protected $z_cos_a = 10;
  protected $z_sin_a = 10;
  protected $solid_colour = 'none';

  public function __construct(&$graph)
  {
    $this->graph = $graph;
    $this->overlay_top = min(1, max(0, $graph->getOption('bar_top_overlay_opacity')));
    $this->overlay_side = min(1, max(0, $graph->getOption('bar_side_overlay_opacity')));
    $this->overlay_front = min(1, max(0, $graph->getOption('bar_front_overlay_opacity')));
    if($this->overlay_top)
      $this->overlay_top_colour = new Colour($graph, $graph->getOption('bar_top_overlay_colour'));
    if($this->overlay_side)
      $this->overlay_side_colour = new Colour($graph, $graph->getOption('bar_side_overlay_colour'));
    if($this->overlay_front)
      $this->overlay_front_colour = new Colour($graph, $graph->getOption('bar_front_overlay_colour'));
    $this->skew_top = (bool)$graph->getOption('skew_top');
    $this->skew_side = (bool)$graph->getOption('skew_side');
    $this->angle = min(89, max(1, $graph->getOption('project_angle', 30)));
  }

  /**
   * Sets the depth of the bar
   */
  public function setDepth($d)
  {
    $this->depth = $d;
    $a = deg2rad($this->angle);
    $this->z_cos_a = $d * cos($a);
    $this->z_sin_a = $d * sin($a);
  }

  /**
   * Projects x,y at fixed depth
   */
  protected function project($x, $y)
  {
    return [$x + $this->z_cos_a, $y - $this->z_sin_a];
  }

  /**
   * Draws the bar
   */
  public function draw($x, $y, $w, $h, $draw_top, $draw_side, $solid_colour)
  {
    $this->solid_colour = $solid_colour;
    $bar = $this->front($x, $y, $w, $h);
    if($draw_top)
      $bar .= $this->top($x, $y, $w, $h);
    if($draw_side)
      $bar .= $this->side($x, $y, $w, $h);
    $bar .= $this->edge($x, $y, $w, $h);
    return $bar;
  }

  /**
   * Draws bar front
   */
  protected function front($x, $y, $w, $h)
  {
    $f = ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'stroke' => 'none'];
    $front = $this->graph->element('rect', $f);
    if($this->overlay_front) {
      $f['fill-opacity'] = $this->overlay_front;
      $f['fill'] = $this->overlay_front_colour;
      $front .= $this->graph->element('rect', $f);
    }
    return $front;
  }

  /**
   * Draws bar top
   */
  protected function top($x, $y, $w, $h)
  {
    $t = $this->top_path($w);
    $xform = new Transform;
    $xform->translate($x, $y);

    // skewing?
    if(isset($t['transform']))
      $xform->add($t['transform']);
    else
      $t['fill'] = $this->solid_colour;
    $t['transform'] = $xform;
    $top = $this->graph->element('path', $t);

    if($this->overlay_top) {
      $t['fill-opacity'] = $this->overlay_top;
      $t['fill'] = $this->overlay_top_colour;
      $top .= $this->graph->element('path', $t);
    }
    return $top;
  }

  /**
   * Draws bar side
   */
  protected function side($x, $y, $w, $h)
  {
    $s = $this->side_path($h);
    $xform = new Transform;
    $xform->translate($x + $w, $y);
    if(isset($s['transform']))
      $xform->add($s['transform']);
    $s['transform'] = $xform;
    $side = $this->graph->element('path', $s);

    if($this->overlay_side) {
      $s['fill-opacity'] = $this->overlay_side;
      $s['fill'] = $this->overlay_side_colour;
      $side .= $this->graph->element('path', $s);
    }
    return $side;
  }

  /**
   * Draws bar edge path
   */
  protected function edge($x, $y, $w, $h)
  {
    $e = [];
    if($h > 0) {
      $e['d'] = new PathData(
        // surround
        'M', $x, $y + $h,
        'l', 0, -$h,
        'l', $this->z_cos_a, -$this->z_sin_a,
        'l', $w, 0,
        'l', 0, $h,
        'l', -$this->z_cos_a, $this->z_sin_a, 'z',

        // vertical
        'M', $x + $w, $y,
        'v', $h,

        // top front and side
        'M', $x, $y,
        'l', $w, 0,
        'l', $this->z_cos_a, -$this->z_sin_a);
    } else {
      $e['d'] = new PathData('M', $x, $y,
        'l', $this->z_cos_a, -$this->z_sin_a,
        'l', $w, 0,
        'l', -$this->z_cos_a, $this->z_sin_a, 'z');
    }
    $e['fill'] = 'none';
    return $this->graph->element('path', $e);
  }

  /**
   * Returns path array for a bar top
   */
  public function top_path($bw)
  {
    $top = [];
    if($this->skew_top) {
      $top['d'] = new PathData('M', 0, 0,
        'l', 0, -$this->depth,
        'l', $bw, 0,
        'l', 0, $this->depth, 'z');
      $top['transform'] = $this->skew(true);
    } else {
      $top['d'] = new PathData('M', 0, 0,
        'l', $bw, 0,
        'l', $this->z_cos_a, -$this->z_sin_a,
        'l', -$bw, 0, 'z');
    }
    $top['stroke'] = 'none';
    return $top;
  }

  /**
   * Returns path array for a bar side
   */
  public function side_path($bh)
  {
    $side = [];
    if($this->skew_side) {
      $side['d'] = new PathData('M', 0, 0,
        'L', $this->depth, 0,
        'l', 0, $bh,
        'l', -$this->depth, 0, 'z');
      $side['transform'] = $this->skew(false);
    } else {
      $side['d'] = new PathData('M', 0, 0,
        'l', $this->z_cos_a, -$this->z_sin_a,
        'l', 0, $bh,
        'l', -$this->z_cos_a, $this->z_sin_a, 'z');
    }
    $side['stroke'] = 'none';
    return $side;
  }

  /**
   * Returns the transform for skewing side or top
   */
  protected function skew($top)
  {
    $xform = new Transform;
    $s_x = 1;
    $s_y = 1;
    if($top) {
      $xform->skewX(-90 + $this->angle);
      $s_y = $this->z_sin_a / $this->depth;
    } else {
      $xform->skewY(-$this->angle);
      $s_x = $this->z_cos_a / $this->depth;
    }

    $xform->scale($s_x, $s_y);
    return $xform;
  }
}
