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

class Donut3DGraph extends Pie3DGraph {

  use DonutGraphTrait;
  protected $edge_class = 'Goat1000\\SVGGraph\\DonutSliceEdge';

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = [];
    // enable flat sides when drawing a gap
    if(isset($settings['donut_slice_gap']) && $settings['donut_slice_gap'] > 0)
      $fs['draw_flat_sides'] = true;

    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  /**
   * Returns the gradient overlay
   */
  protected function getEdgeOverlay($x_centre, $y_centre, $depth, $clip_path, $edge)
  {
    if(!$edge->inner())
      return parent::getEdgeOverlay($x_centre, $y_centre, $depth, $clip_path, $edge);

    // use radius of whole pie unless slice values are set
    $radius_x = $this->radius_x;
    $radius_y = $this->radius_y;
    if($edge->slice['radius_x'] && $edge->slice['radius_y']) {
      $radius_x = $edge->slice['radius_x'];
      $radius_y = $edge->slice['radius_y'];
    }

    $ratio = $edge->getInnerRatio();
    $radius_x *= $ratio;
    $radius_y *= $ratio;

    // clip a gradient-filled rect to the edge shape
    $cx = new Number($x_centre);
    $cy = new Number($y_centre);
    $gradient_id = $this->defs->addGradient($this->getOption('depth_shade_gradient'));
    $rect = [
      'x' => $x_centre - $radius_x,
      'y' => $y_centre - $radius_y,
      'width' => $radius_x * 2.0,
      'height' => $radius_y * 2.0 + $this->depth + 2.0,
      'fill' => 'url(#' . $gradient_id . ')',

      // rotate the rect to reverse the gradient
      'transform' => "rotate(180,{$cx},{$cy})",
    ];

    // clip a group containing the rotated rect
    $g = [ 'clip-path' => 'url(#' . $clip_path . ')' ];
    return $this->element('g', $g, null, $this->element('rect', $rect));
  }
}

