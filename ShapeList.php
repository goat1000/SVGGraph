<?php
/**
 * Copyright (C) 2015-2021 Graham Breach
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
 * Arbitrary shapes for adding to graphs
 */
class ShapeList {

  const ABOVE = 1;
  const BELOW = 0;

  private $graph;
  private $shapes = [];

  public function __construct(&$graph)
  {
    $this->graph =& $graph;
  }

  /**
   * Load shapes from options list
   */
  public function load(&$settings)
  {
    if(!isset($settings['shape']))
      return;

    if(!is_array($settings['shape']) || !isset($settings['shape'][0]))
      throw new \Exception('Malformed shape option');

    if(!is_array($settings['shape'][0])) {
      $this->addShape($settings['shape']);
      return;
    }

    foreach($settings['shape'] as $shape)
      $this->addShape($shape);
  }

  /**
   * Draw all the shapes for the selected depth
   */
  public function draw($depth)
  {
    $content = [];
    foreach($this->shapes as $shape) {
      if($shape->depth($depth))
        $content[] = $shape->draw($this->graph);
    }
    return implode($content);
  }

  /**
   * Adds a shape from config array
   */
  private function addShape(&$shape_array)
  {
    $this->shapes[] = $this->getShape($shape_array);
  }

  /**
   * Returns a shape class
   */
  public function getShape(&$shape_array)
  {
    $shape = $shape_array[0];
    unset($shape_array[0]);

    $class_map = [
      'circle' => 'Goat1000\\SVGGraph\\Circle',
      'ellipse' => 'Goat1000\\SVGGraph\\Ellipse',
      'rect' => 'Goat1000\\SVGGraph\\Rect',
      'line' => 'Goat1000\\SVGGraph\\Line',
      'polyline' => 'Goat1000\\SVGGraph\\PolyLine',
      'polygon' => 'Goat1000\\SVGGraph\\Polygon',
      'path' => 'Goat1000\\SVGGraph\\Path',
      'marker' => 'Goat1000\\SVGGraph\\MarkerShape',
      'figure' => 'Goat1000\\SVGGraph\\FigureShape',
      'image' => 'Goat1000\\SVGGraph\\Image',
      'text' => 'Goat1000\\SVGGraph\\TextShape',
    ];

    if(isset($class_map[$shape]) && class_exists($class_map[$shape])) {
      $depth = ShapeList::BELOW;
      if(isset($shape_array['depth']) && $shape_array['depth'] == 'above')
        $depth = ShapeList::ABOVE;

      if(isset($shape_array['clip_to_grid']) && $shape_array['clip_to_grid'] &&
        method_exists($this->graph, 'gridClipPath')) {
        $clip_id = $this->graph->gridClipPath();
        $shape_array['clip-path'] = 'url(#' . $clip_id . ')';
      }
      unset($shape_array['depth'], $shape_array['clip_to_grid']);
      return new $class_map[$shape]($shape_array, $depth);
    }
    throw new \Exception('Unknown shape [' . $shape . ']');
  }
}

