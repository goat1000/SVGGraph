<?php
/**
 * Copyright (C) 2021 Graham Breach
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

class TextShape extends Shape {
  protected $element = 'text';
  protected $required = ['text','x','y'];
  protected $transform_pairs = [ ['x', 'y'] ];

  /**
   * Override default attributes from Shape
   */
  protected $attrs = [
    'fill' => '#000',
    'font_size' => 14,
    'font' => 'Arial',
  ];

  /**
   * Override to draw text element
   */
  protected function drawElement(&$graph, &$attributes)
  {
    $content = $attributes['text'];
    $font = $attributes['font'];
    $spacing = isset($attributes['line-spacing']) ? $attributes['line-spacing'] :
      $attributes['font-size'];
    $align = isset($attributes['text-align']) ? $attributes['text-align'] : '';

    // remove SVGGraph's shape options
    $unset_list = ['text', 'font', 'line-spacing', 'text-align'];
    foreach($unset_list as $a)
      unset($attributes[$a]);

    $t = new Text($graph, $font);
    $align_map = ['right' => 'end', 'centre' => 'middle'];
    if(isset($align_map[$align]))
      $attributes['text-anchor'] = $align_map[$align];
    $attributes['font-family'] = $font;

    return $t->text($content, $spacing, $attributes);
  }
}

