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

class PolyLine extends Shape {
  protected $element = 'polyline';
  protected $required = ['points'];

  public function __construct(&$attrs, $depth)
  {
    parent::__construct($attrs, $depth);
    if(!is_array($this->attrs['points']))
      $this->attrs['points'] = explode(' ', $this->attrs['points']);
    $count = count($this->attrs['points']);
    if($count < 4 || $count % 2 == 1)
      throw new \Exception('Shape must have at least 2 pairs of points');
  }

  /**
   * Override to transform pairs of points
   */
  protected function transformCoordinates(&$attributes)
  {
    $count = count($attributes['points']);
    for($i = 0; $i < $count; $i += 2) {
      $x = $attributes['points'][$i];
      $y = $attributes['points'][$i + 1];
      $coords = $this->coords->transformCoords($x, $y);
      $attributes['points'][$i] = $coords[0];
      $attributes['points'][$i + 1] = $coords[1];
    }
  }

  /**
   * Override to build the points attribute
   */
  protected function drawElement(&$graph, &$attributes)
  {
    $attributes['points'] = implode(' ', $attributes['points']);
    return parent::drawElement($graph, $attributes);
  }
}

