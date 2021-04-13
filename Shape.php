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

abstract class Shape {

  protected $depth = ShapeList::BELOW;
  protected $element = '';
  protected $link = null;
  protected $link_target = '_blank';
  protected $coords = null;
  protected $autohide = false;

  /**
   * attributes required to draw shape
   */
  protected $required = [];

  /**
   * attributes that support coordinate transformation
   */
  protected $transform = [];

  /**
   * coordinate pairs for dependent transforns - don't include them in
   * $transform or they will be transformed twice
   */
  protected $transform_pairs = [];

  /**
   * colour gradients/patterns, and whether to allow gradients
   */
  private $colour_convert = [
    'stroke' => false,
    'fill' => true
  ];

  /**
   * default attributes for all shapes
   */
  protected $attrs = [
    'stroke' => '#000',
    'fill' => 'none'
  ];

  public function __construct(&$attrs, $depth)
  {
    $this->attrs = array_merge($this->attrs, $attrs);
    $this->depth = $depth;

    $missing = [];
    foreach($this->required as $opt)
      if(!isset($this->attrs[$opt]))
        $missing[] = $opt;

    if(count($missing))
      throw new \Exception($this->element . ' attribute(s) not found: ' .
        implode(', ', $missing));

    if(isset($this->attrs['href']))
      $this->link = $this->attrs['href'];
    if(isset($this->attrs['xlink:href']))
      $this->link = $this->attrs['xlink:href'];
    if(isset($this->attrs['target']))
      $this->link_target = $this->attrs['target'];
    if(isset($this->attrs['autohide'])) {
      $hide = 0;
      $show = isset($this->attrs['opacity']) ? $this->attrs['opacity'] : 1;
      if(isset($this->attrs['autohide_opacity'])) {
        if(is_array($this->attrs['autohide_opacity']))
          list($hide, $show) = $this->attrs['autohide_opacity'];
        else
          $hide = $this->attrs['autohide_opacity'];
      }
      $this->autohide = [$hide, $show];
    }

    $clean = ['href', 'xlink:href', 'target', 'autohide', 'autohide_opacity'];
    foreach($clean as $att)
      unset($this->attrs[$att]);
  }

  /**
   * returns true if the depth is correct
   */
  public function depth($d)
  {
    return $this->depth == $d;
  }

  /**
   * draws the shape
   */
  public function draw(&$graph)
  {
    $this->coords = new Coords($graph);

    $attributes = [];
    foreach($this->attrs as $attr => $value) {
      if($value !== null) {
        $val = $value;
        if(isset($this->transform[$attr])) {
          $val = $this->coords->transform($value, $this->transform[$attr]);
        } elseif(isset($this->colour_convert[$attr])) {
          $val = new Colour($graph, $value, $this->colour_convert[$attr]);
        }
        $attr = str_replace('_', '-', $attr);
        $attributes[$attr] = $val;
      }
    }
    $this->transformCoordinates($attributes);

    if($this->autohide) {
      $graph->javascript->autoHide($attributes, $this->autohide[0],
        $this->autohide[1]);
    }

    $element = $this->drawElement($graph, $attributes);
    if($this->link !== null) {
      $link = ['xlink:href' => $this->link];
      if($this->link_target !== null)
        $link['target'] = $this->link_target;
      $element = $graph->element('a', $link, null, $element);
    }
    return $element;
  }

  /**
   * Transform coordinate pairs
   */
  protected function transformCoordinates(&$attr)
  {
    if(empty($this->transform_pairs))
      return;
    foreach($this->transform_pairs as $pair) {
      list($x, $y) = $pair;
      $coords = $this->coords->transformCoords($attr[$x], $attr[$y]);
      list($attr[$x], $attr[$y]) = $coords;
    }
  }

  /**
   * Performs the conversion to SVG fragment
   */
  protected function drawElement(&$graph, &$attributes)
  {
    return $graph->element($this->element, $attributes);
  }
}

