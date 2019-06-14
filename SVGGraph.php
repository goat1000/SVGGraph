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

class SVGGraph {

  const VERSION = 'SVGGraph 3.0.1';
  private $width = 100;
  private $height = 100;
  private $settings = [];
  private $values = [];
  private $links = null;
  private $colours = null;
  protected static $last_instance = null;

  public function __construct($w, $h, $settings = null)
  {
    $this->width = $w;
    $this->height = $h;

    if(is_array($settings)) {
      // structured_data, when FALSE disables structure
      if(isset($settings['structured_data']) && !$settings['structured_data'])
        unset($settings['structure']);
      $this->settings = $settings;
    }
    $this->colours = new Colours;
  }

  /**
   * Prevent direct access to members
   */
  public function __set($name, $val)
  {
    if($name == 'values' || $name == 'links' || $name == 'colours') {
      throw new \BadMethodCallException('Modifying $graph->' . $name .
        ' directly is not supported - please use the $graph->' . $name .
        '() function.');
    }
  }

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
    $full_class = '\\Goat1000\\SVGGraph\\' . $class;
    if(!class_exists($full_class)) {
      throw new \InvalidArgumentException('Unknown graph type: ' . $class);
    }

    if(!is_subclass_of($full_class, '\\Goat1000\\SVGGraph\\Graph')) {
      throw new \InvalidArgumentException('Not a graph class: ' . $class);
    }

    $g = new $full_class($this->width, $this->height, $this->settings);
    $g->values($this->values);
    $g->links($this->links);
    $g->colours($this->colours);
    return $g;
  }

  /**
   * Fetch the content
   */
  public function fetch($class, $header = true, $defer_js = true)
  {
    SVGGraph::$last_instance = $this->setup($class);
    return SVGGraph::$last_instance->fetch($header, $defer_js);
  }

  /**
   * Pass in the type of graph to display
   */
  public function render($class, $header = true, $content_type = true,
    $defer_js = false)
  {
    SVGGraph::$last_instance = $this->setup($class);
    return SVGGraph::$last_instance->render($header, $content_type, $defer_js);
  }

  /**
   * Fetch the Javascript for ALL graphs that have been Fetched
   */
  public static function fetchJavascript()
  {
    if(!is_null(SVGGraph::$last_instance))
      return SVGGraph::$last_instance->fetchJavascript(true, true, true);
  }
}

