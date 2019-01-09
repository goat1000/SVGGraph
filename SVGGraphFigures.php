<?php
/**
 * Copyright (C) 2018 Graham Breach
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

class SVGGraphFigures {

  private $graph;
  private $figure_map = array();

  public function __construct(&$graph)
  {
    $this->graph = $graph;
  }

  /**
   * Load figures from options list
   */
  public function Load(&$settings)
  {
    if(!isset($settings['figure']))
      return;

    if(!is_array($settings['figure']) || !isset($settings['figure'][0]))
      throw new Exception('Malformed figure option');

    if(!is_array($settings['figure'][0])) {
      $this->AddFigure($settings['figure']);
    } else {
      foreach($settings['figure'] as $figure) {
        $this->AddFigure($figure);
      }
    }
  }

  /**
   * Adds a figure to the list
   */
  private function AddFigure($figure_array)
  {
    $name = array_shift($figure_array);
    if(isset($this->figure_map[$name]))
      throw new Exception("Figure [{$name}] defined more than once");
    $content = '';
    if(!is_array($figure_array[0])) {
      $shape = $this->graph->shapes->GetShape($figure_array);
      $content .= $shape->Draw($this->graph);
    } else {
      foreach($figure_array as $s) {
        $shape = $this->graph->shapes->GetShape($s);
        $content .= $shape->Draw($this->graph);
      }
    }
    $id = $this->graph->symbols->Define($content);
    $this->figure_map[$name] = $id;
  }

  /**
   * Returns a figure's symbol ID by name
   */
  public function GetFigure($name)
  {
    if(isset($this->figure_map[$name]))
      return $this->figure_map[$name];
    return null;
  }
}

