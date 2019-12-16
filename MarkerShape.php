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

class MarkerShape extends Shape {
  protected $element = 'use';
  protected $required = ['type', 'x', 'y'];
  protected $transform = ['size' => 'y'];
  protected $transform_pairs = [ ['x', 'y'] ];

  /**
   * Override to draw a marker
   */
  protected function drawElement(&$graph, &$attributes)
  {
    $markers = new Markers($graph);
    $size = isset($attributes['size']) ? $attributes['size'] : 10;
    $stroke_width = isset($attributes['stroke-width']) ?
      $attributes['stroke-width'] : 1;
    $opacity = isset($attributes['opacity']) ?
      $attributes['opacity'] : 1;
    $angle = isset($attributes['angle']) ?
      $attributes['angle'] : 0;
    $id = $markers->create($attributes['type'], $size,
      $attributes['fill'], $stroke_width, $attributes['stroke'],
      $opacity, $angle);

    $remove = ['type', 'size', 'fill', 'stroke', 'stroke-width',
      'opacity', 'angle'];
    $use = $attributes;
    foreach($remove as $key) {
      unset($use[$key]);
    }

    // clip-path must be applied to <g> to prevent being offset with <use>
    if(isset($use['clip-path'])) {
      $group = ['clip-path' => $use['clip-path']];
      unset($use['clip-path']);
      $e = $graph->element('g', $group, null,
        $graph->defs->useSymbol($id, $use));
    } else {
      $e = $graph->defs->useSymbol($id, $use);
    }
    return $e;
  }
}

