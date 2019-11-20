<?php
/**
 * Copyright (C) 2009-2019 Graham Breach
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

trait SVGGraphTrait {

  /**
   * Assign values, either from an array or from numeric arguments
   */
  public function values($values)
  {
    $this->values = is_array($values) ? $values : func_get_args();
  }

  /**
   * Assign links to data items
   */
  public function links($links)
  {
    $this->links = is_array($links) ? $links : func_get_args();
  }

  /**
   * Assign a single colour set for use across datasets
   */
  public function colours($colours)
  {
    $this->colours = new Colours($colours);
  }

  /**
   * Sets colours for a single dataset
   */
  public function colourSet($dataset, $colours)
  {
    $this->colours->set($dataset, $colours);
  }

  /**
   * Sets up RGB colour range
   */
  public function colourRangeRGB($dataset, $r1, $g1, $b1, $r2, $g2, $b2)
  {
    $this->colours->rangeRGB($dataset, $r1, $g1, $b1, $r2, $g2, $b2);
  }

  /**
   * RGB colour range from hex codes
   */
  public function colourRangeHexRGB($dataset, $c1, $c2)
  {
    $this->colours->rangeHexRGB($dataset, $c1, $c2);
  }

  /**
   * Sets up HSL colour range
   */
  public function colourRangeHSL($dataset, $h1, $s1, $l1, $h2, $s2, $l2,
    $reverse = false)
  {
    $this->colours->rangeHSL($dataset, $h1, $s1, $l1, $h2, $s2, $l2, $reverse);
  }

  /**
   * HSL colour range from hex codes
   */
  public function colourRangeHexHSL($dataset, $c1, $c2, $reverse = false)
  {
    $this->colours->rangeHexHSL($dataset, $c1, $c2, $reverse);
  }

  /**
   * Sets up HSL colour range from RGB values
   */
  public function colourRangeRGBtoHSL($dataset, $r1, $g1, $b1, $r2, $g2, $b2,
    $reverse = false)
  {
    $this->colours->rangeRGBtoHSL($dataset, $r1, $g1, $b1, $r2, $g2, $b2,
      $reverse);
  }

  /**
   * Instantiate the correct class
   */
  private function setup($class)
  {
    if(!strstr($class, '\\')) {
        $class = __NAMESPACE__ . '\\' . $class;
    }

    if(!class_exists($class)) {
      throw new \InvalidArgumentException('Unknown graph type: ' . $class);
    }

    if(!is_subclass_of($class, '\\Goat1000\\SVGGraph\\Graph')) {
      throw new \InvalidArgumentException('Not a graph class: ' . $class);
    }

    $g = new $class($this->width, $this->height, $this->settings);
    $g->subgraph = $this->subgraph;
    $g->values($this->values);
    $g->links($this->links);
    $g->colours($this->colours);
    $g->subgraphs($this->subgraphs);
    return $g;
  }

  /**
   * Returns a sub-graph
   */
  public function subgraph($type, $x, $y, $w, $h, $settings = null,
    $extra = null)
  {
    if(!is_string($x) && $x < 0)
      $x = $this->width + $x;
    if(!is_string($y) && $y < 0)
      $y = $this->height + $y;
    if(!is_string($w) && $w <= 0)
      $w = $this->width - $x + $w;
    if(!is_string($h) && $h <= 0)
      $h = $this->height - $y + $h;

    if($settings === null)
      $settings = $this->settings;
    if(is_array($extra))
      $settings = array_merge($settings, $extra);
    $sg = new Subgraph($type, $x, $y, $w, $h, $settings);
    $sg->setColours(clone $this->colours);
    $this->subgraphs[] = $sg;
    return $sg;
  }

  /**
   * Replaces the list of subgraphs
   */
  public function setSubgraphs($list)
  {
    $this->subgraphs = $list;
  }
}

