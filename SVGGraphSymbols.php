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

class SVGGraphSymbols {

  private $graph;
  private $symbols = array();
  private $use_count = array();
  private $empty_use;

  public function __construct(&$graph)
  {
    $this->graph = $graph;
    $this->empty_use = $graph->GetOption('empty_use');
  }

  /**
   * Defines a symbol, returning its ID
   */
  public function Define($content)
  {
    // if this is a duplicate, return existing ID
    foreach($this->symbols as $id => $def) {
      if($def == $content)
        return $id;
    }

    $id = $this->graph->NewID();
    $this->symbols[$id] = $content;
    return $id;
  }

  /**
   * Uses an existing symbol
   */
  public function UseSymbol($id, $attr, $style = null)
  {
    if(!isset($this->symbols[$id]))
      throw new Exception("Symbol {$id} not defined");

    if(isset($this->use_count[$id]))
      ++$this->use_count[$id];
    else
      $this->use_count[$id] = 1;

    $uattr = array_merge($attr, array('xlink:href' => "#{$id}"));
    return $this->graph->Element('use', $uattr, $style,
      $this->empty_use ? '' : null);
  }

  /**
   * Returns symbol use count
   */
  public function UseCount($id)
  {
    if(isset($this->use_count[$id]))
      return $this->use_count[$id];
    return 0;
  }

  /**
   * Outputs the list of used definitions
   */
  public function Definitions()
  {
    $defs = '';
    foreach($this->use_count as $id => $count) {
      $defs .= $this->graph->Element('symbol', null, null,
        $this->graph->Element('g', array('id' => $id), null,
          $this->symbols[$id]));
    }

    return $defs;
  }
}

