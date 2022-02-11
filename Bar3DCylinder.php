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

class Bar3DCylinder extends Bar3D {

  protected $ellipse;
  protected $arc_path;
  protected $cyl_offset_x;
  protected $cyl_offset_y;
  protected $shade_gradient_id;

  public function __construct(&$graph)
  {
    parent::__construct($graph);
    $gradient = $graph->getOption('depth_shade_gradient');
    if(is_array($gradient))
      $this->shade_gradient_id = $graph->defs->addGradient($gradient);
  }

  /**
   * Calculates the a and b radii of the ellipse filling the parallelogram
   */
  protected function findEllipse()
  {
    $alpha = deg2rad($this->angle / 2);
    $x = $this->depth * cos($alpha) / 2;
    $y = $this->depth * sin($alpha) / 2;
    $dydx = -$y / $x;

    $bsq = pow($y, 2) - $x * $y * $dydx;
    $asq = pow($x, 2) / (1 - $y / ($y - $x * $dydx));

    $a = sqrt($asq);
    $b = sqrt($bsq);

    // now find the vertical
    $alpha2 = deg2rad(- $this->angle / 2 - 90);
    $dydx2 = tan($alpha2);
    $ysq = $bsq / (pow($dydx2, 2) * ($asq / $bsq) + 1);
    $xsq = $asq - $asq * $ysq / $bsq;

    $x1 = sqrt($xsq);
    $y1 = -sqrt($ysq);
    $this->ellipse = compact('a', 'b', 'x1', 'y1');

    // create the arc path
    $r = -$this->angle / 2;
    $rr = deg2rad($r);
    $x1a = -($x1 * cos($rr) + $y1 * sin($rr));
    $y1a = -($x1 * sin($rr) - $y1 * cos($rr));
    $x2 = -2 * $x1a;
    $y2 = -2 * $y1a;
    $this->cyl_offset_x = $x1a;
    $this->cyl_offset_y = $y1a;
    $this->arc_path = new PathData('a', $a, $b, $r, 1, 0, $x2, $y2);
  }

  /**
   * Sets the depth of the bar
   */
  public function setDepth($d)
  {
    parent::setDepth($d);
    $this->findEllipse();
  }

  /**
   * Draws bar front
   */
  protected function front($x, $y, $w, $h)
  {
    $x += $this->cyl_offset_x;
    $y += $this->cyl_offset_y;
    $path = new PathData('M', $x, $y, 'v', $h);
    $path->add($this->arc_path);
    $path->add('v', -$h);
    $f = ['d' => $path, 'stroke' => 'none'];
    $front = $this->graph->element('path', $f);

    if($this->shade_gradient_id) {
      $f['fill'] = 'url(#' . $this->shade_gradient_id . ')';
      $front .= $this->graph->element('path', $f);
    }

    $f = ['d' => $path, 'fill' => 'none', 'stroke-linejoin' => 'bevel'];
    $front .= $this->graph->element('path', $f);
    return $front;
  }

  /**
   * Draws bar top
   */
  protected function top($x, $y, $w, $h)
  {
    $r = -$this->angle / 2;
    $xform = new Transform;
    $xform->translate($x, $y);
    $xform->rotate($r);
    $t = [
      'cx' => 0, 'cy' => 0,
      'rx' => $this->ellipse['a'], 'ry' => $this->ellipse['b'],
      'transform' => $xform,
      'fill' => $this->solid_colour,
    ];
    if($this->overlay_top)
      $t['stroke'] = 'none';
    $top = $this->graph->element('ellipse', $t);

    if($this->overlay_top) {
      $t['fill-opacity'] = $this->overlay_top;
      $t['fill'] = $this->overlay_top_colour;
      unset($t['stroke']);
      $top .= $this->graph->element('ellipse', $t);
    }
    return $top;
  }

  /**
   * Draws bar side
   */
  protected function side($x, $y, $w, $h)
  {
    return '';
  }

  /**
   * Draws bar edge path
   */
  protected function edge($x, $y, $w, $h)
  {
    // cylinder edge is drawn with front
    return '';
  }
}
