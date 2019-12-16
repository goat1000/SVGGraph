<?php
/**
 * Copyright (C) 2018-2019 Graham Breach
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

class Figures {

  private $graph;
  private $figure_map = [];

  public function __construct(&$graph)
  {
    $this->graph =& $graph;
  }

  /**
   * Load figures from options list
   */
  public function load(&$settings)
  {
    if(!isset($settings['figure']))
      return;

    if(!is_array($settings['figure']) || !isset($settings['figure'][0]))
      throw new \Exception('Malformed figure option.');

    if(!is_array($settings['figure'][0])) {
      $this->addFigure($settings['figure']);
    } else {
      foreach($settings['figure'] as $figure) {
        $this->addFigure($figure);
      }
    }
  }

  /**
   * Adds a figure to the list
   */
  private function addFigure($figure_array)
  {
    $name = array_shift($figure_array);
    if(isset($this->figure_map[$name]))
      throw new \Exception('Figure [' . $name . '] defined more than once.');
    $content = '';
    if(!is_array($figure_array[0])) {
      $shape = $this->graph->shapes->getShape($figure_array);
      $content .= $shape->draw($this->graph);
    } else {
      foreach($figure_array as $s) {
        $shape = $this->graph->shapes->getShape($s);
        $content .= $shape->draw($this->graph);
      }
    }
    $id = $this->graph->defs->defineSymbol($content);
    $this->figure_map[$name] = $id;
  }

  /**
   * Returns a figure's symbol ID by name
   */
  public function getFigure($name)
  {
    if(isset($this->figure_map[$name]))
      return $this->figure_map[$name];
    return null;
  }
}

