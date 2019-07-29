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

class Subgraph {

  use SVGGraphTrait;

  private $type;
  private $x;
  private $y;
  private $width;
  private $height;
  private $settings;
  private $values = [];
  private $links = null;
  private $colours = null;
  private $subgraph = true;
  private $subgraphs = [];

  public function __construct($type, $x, $y, $w, $h, $settings)
  {
    $this->type = $type;
    $this->x = $x;
    $this->y = $y;
    $this->width = $w;
    $this->height = $h;
    if($settings === null)
      $settings = [];

    $settings['graph_x'] = $x;
    $settings['graph_y'] = $y;
    $this->settings = $settings;
    $this->colours = new Colours;
  }

  /**
   * Used to duplicate parent graph's colours
   */
  public function setColours($colours)
  {
    $this->colours = $colours;
  }

  /**
   * Fetches the graph content
   */
  public function fetch()
  {
    $graph = $this->setup($this->type);

    // no header, defer javascript
    return $graph->fetch(false, true);
  }
}

