<?php
/**
 * Copyright (C) 2009-2023 Graham Breach
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

  use SVGGraphTrait;

  const VERSION = 'SVGGraph 3.20';
  private $width = 100;
  private $height = 100;
  private $settings = [];
  private $values = [];
  private $links = null;
  private $colours = null;
  private $subgraph = false;
  private $subgraphs = [];
  protected static $last_instance = null;

  public function __construct($w, $h, $settings = null, $subgraph = false)
  {
    $this->subgraph = $subgraph;
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
    if(SVGGraph::$last_instance === null)
      return '';
    return SVGGraph::$last_instance->fetchJavascript(true, true);
  }
}

