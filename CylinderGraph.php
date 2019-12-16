<?php
/**
 * Copyright (C) 2012-2019 Graham Breach
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

class CylinderGraph extends Bar3DGraph {

  protected $transform;
  protected $arc_path;
  protected $cyl_offset_x;
  protected $cyl_offset_y;
  protected $shade_gradient_id;

  /**
   * Initialize cylinder settings
   */
  protected function barSetup()
  {
    parent::barSetup();

    // set the gradient overlay
    $gradient = $this->getOption('depth_shade_gradient');
    if(is_array($gradient))
      $this->shade_gradient_id = $this->defs->addGradient($gradient);
  }

  /**
   * Set the bar width and space, create the top
   */
  protected function setBarWidth($width, $space)
  {
    parent::setBarWidth($width, $space);

    // use the ellipse info to create the bottom arc
    $ellipse = $this->findEllipse();
    $r = -$this->project_angle / 2;
    $rr = deg2rad($r);
    $x1 = -($ellipse['x1'] * cos($rr) + $ellipse['y1'] * sin($rr));
    $y1 = -($ellipse['x1'] * sin($rr) - $ellipse['y1'] * cos($rr));
    $x2 = -2 * $x1;
    $y2 = -2 * $y1;
    $this->cyl_offset_x = $x1;
    $this->cyl_offset_y = $y1;
    $a = $ellipse['a'];
    $b = $ellipse['b'];
    $this->arc_path = new PathData('a', $a, $b, $r, 1, 0, $x2, $y2);
  }

  /**
   * Add translation to the bar group
   */
  protected function barGroup()
  {
    $group = parent::barGroup();

    // translation for cylinders added to 3D bar offset
    list($sx, $sy) = $this->project(0, 0, $this->calculated_bar_width);
    $this->tx += ($this->calculated_bar_width + $sx) / 2;
    $this->ty += $sy / 2;
    $xform = new Transform;
    $xform->translate($this->tx, $this->ty);
    $group['transform'] = $xform;
    return $group;
  }

  /**
   * Creates the ellipse for the top of the cylinder, returns id of symbol
   */
  protected function barTop()
  {
    $ellipse = $this->findEllipse();
    $r = -$this->project_angle / 2;
    $xform = new Transform;
    $xform->rotate($r);
    $top = [
      'cx' => 0, 'cy' => 0,
      'rx' => $ellipse['a'], 'ry' => $ellipse['b'],
      'transform' => $xform,
    ];

    $ellipse = $this->element('ellipse', $top);
    $top_id = $this->defs->defineSymbol($ellipse);
    return $top_id;
  }

  /**
   * Calculates the a and b radii of the ellipse filling the parallelogram
   */
  protected function findEllipse()
  {
    $alpha = deg2rad($this->project_angle / 2);
    $x = $this->calculated_bar_width * cos($alpha) / 2;
    $y = $this->calculated_bar_width * sin($alpha) / 2;
    $dydx = -$y / $x;

    $bsq = pow($y, 2) - $x * $y * $dydx;
    $asq = pow($x, 2) / (1 - $y / ($y - $x * $dydx));

    $a = sqrt($asq);
    $b = sqrt($bsq);

    // now find the vertical
    $alpha2 = deg2rad(- $this->project_angle / 2 - 90);
    $dydx2 = tan($alpha2);
    $ysq = $bsq / (pow($dydx2, 2) * ($asq / $bsq) + 1);
    $xsq = $asq - $asq * $ysq / $bsq;

    $x1 = sqrt($xsq);
    $y1 = -sqrt($ysq);
    return compact('a', 'b', 'x1', 'y1');
  }

  /**
   * Returns the SVG code for a 3D cylinder
   */
  protected function bar3D($item, &$bar, $top, $index, $dataset = null,
    $start = null, $axis = null)
  {
    $pos = $this->barY($item->value, $bar, $start, $axis);
    if($pos === null || $pos > $this->height - $this->pad_bottom)
      return '';

    $cyl_top = '';
    if($top) {
      $xform = new Transform;
      $xform->translate($bar['x'], $bar['y']);
      $top = ['transform' => $xform];
      $top['fill'] = $this->getColour($item, $index, $dataset, false, false);
      $cyl_top = $this->defs->useSymbol($this->top_id, $top);
    }

    $x = $bar['x'] + $this->cyl_offset_x;
    $y = $bar['y'] + $this->cyl_offset_y;
    $h = $bar['height'];
    $path = new PathData('M', $x, $y, 'v', $h);
    $path->add($this->arc_path);
    $path->add('v', -$h, 'z');
    $side = ['d' => $path, 'stroke-linejoin' => 'bevel'];
    $cyl_side = $this->element('path', $side);

    if($this->shade_gradient_id) {
      $side['fill'] = 'url(#' . $this->shade_gradient_id . ')';
      $cyl_side .= $this->element('path', $side);
    }

    return $cyl_side . $cyl_top;
  }
}

