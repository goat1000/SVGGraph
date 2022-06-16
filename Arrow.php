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

/**
 * A class for drawing arrows
 */
class Arrow {

  protected $a;
  protected $b;
  protected $head_size = 7;
  protected $head_colour = '#000';

  public function __construct(Point $a, Point $b)
  {
    $this->a = $a;
    $this->b = $b;
  }

  /**
   * Sets the arrow head size (min 2 pixels)
   */
  public function setHeadSize($size)
  {
    $this->head_size = max(2, $size);
  }

  public function setHeadColour($colour)
  {
    $this->head_colour = $colour;
  }

  /**
   * Returns the arrow head as the ID of a <marker> element
   */
  protected function getArrowHead($graph)
  {
    $sz = new Number($this->head_size);
    $point = 75; // sharpness of arrow
    $marker = [
      'viewBox' => "0 0 {$point} 100",
      'markerWidth' => $sz,
      'markerHeight' => $sz,
      'refX' => $point,
      'refY' => 50,
      'orient' => 'auto',
    ];
    $pd = new PathData('M', 0, 0, 'L', $point, 50, 'L', 0, 100, 'z');
    $path = [
      'd' => $pd,
      'stroke' => $this->head_colour,
      'fill' => $this->head_colour,
    ];
    $marker_content = $graph->element('path', $path);
    return $graph->defs->addElement('marker', $marker, $marker_content);
  }

  /**
   * Returns the PathData for an arrow line
   */
  protected function getArrowPath()
  {
    return new PathData('M', $this->a, $this->b);
  }

  /**
   * Returns the arrow element
   */
  public function draw($graph, $style = null)
  {
    $head_id = $this->getArrowHead($graph);
    $p = $this->getArrowPath();

    $path = [
      'd' => $p,
      'marker-end' => 'url(#' . $head_id . ')',
      'fill' => 'none',
    ];
    if(is_array($style))
      $path = array_merge($style, $path);
    return $graph->element('path', $path);
  }
}

