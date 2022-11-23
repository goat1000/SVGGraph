<?php
/**
 * Copyright (C) 2019-2022 Graham Breach
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
 * A class for the <defs> element
 */
class Defs {

  private $graph;
  private $defs = [];
  private $gradients = null;
  private $patterns = null;
  private $symbols = null;
  private $filters = null;
  private $elements = [];

  public function __construct(&$graph)
  {
    $this->graph =& $graph;
  }

  /**
   * Add a string to the defs block
   */
  public function add($def)
  {
    $this->defs[] = $def;
  }

  /**
   * Adds an element to the defs, returning its ID, or the ID
   * of an existing def with same content
   */
  public function addElement($element, $attrs, $content = '')
  {
    $ehash = hash('md5', $element . ':' . serialize($attrs) . ':' . $content);
    if(isset($this->elements[$ehash]))
      return $this->elements[$ehash];

    $attrs['id'] = $this->graph->newID();
    $this->elements[$ehash] = $attrs['id'];
    $this->add($this->graph->element($element, $attrs, null, $content));
    return $attrs['id'];
  }

  /**
   * Return the defs block, or an empty string if none
   */
  public function get()
  {
    // insert gradients, patterns, symbols
    if($this->gradients !== null)
      $this->gradients->makeGradients($this);
    if($this->patterns !== null)
      $this->patterns->makePatterns($this);
    if($this->symbols !== null)
      $this->defs[] = $this->symbols->definitions();
    if($this->filters !== null)
      $this->filters->makeFilters($this);

    if(count($this->defs) == 0)
      return '';

    return $this->graph->element('defs', null, null, implode('', $this->defs));
  }

  /**
   * Adds a gradient to the list, returning the element ID for use in url
   */
  public function addGradient($colours, $key = null, $radial = false)
  {
    if($this->gradients === null)
      $this->gradients = new GradientList($this->graph);
    return $this->gradients->addGradient($colours, $key, $radial);
  }

  /**
   * Returns the colour at a point in the selected gradient
   */
  public function getGradientColour($key, $position)
  {
    if($this->gradients === null)
      return 'none';

    return $this->gradients->getColour($key, $position);
  }

  /**
   * Adds a pattern, returning the element ID
   */
  public function addPattern($pattern)
  {
    if($this->patterns === null)
      $this->patterns = new PatternList($this->graph);
    return $this->patterns->add($pattern);
  }

  /**
   * Defines a symbol
   */
  public function defineSymbol($content)
  {
    if($this->symbols === null)
      $this->symbols = new Symbols($this->graph);
    return $this->symbols->define($content);
  }

  /**
   * Uses a symbol
   */
  public function useSymbol($id, $attr, $style = null)
  {
    // this should not happen - Symbols class will throw anyway
    if($this->symbols === null)
      $this->symbols = new Symbols($this->graph);
    return $this->symbols->useSymbol($id, $attr, $style);
  }

  /**
   * Returns the use count for a symbol
   */
  public function symbolUseCount($id)
  {
    if($this->symbols === null)
      return 0;
    return $this->symbols->useCount($id);
  }

  /**
   * Adds a filter
   */
  public function addFilter($type, $params = null)
  {
    if($this->filters === null)
      $this->filters = new FilterList($this->graph);
    return $this->filters->add($type, $params);
  }

  /**
   * Returns id of shadow, if enabled
   */
  public function getShadow()
  {
    if(!$this->graph->getOption('show_shadow'))
      return null;

    $filter_id = $this->addFilter('shadow', [
      'opacity' => $this->graph->getOption('shadow_opacity'),
      'offset_x' => $this->graph->getOption('shadow_offset_x'),
      'offset_y' => $this->graph->getOption('shadow_offset_y'),
      'blur' => $this->graph->getOption('shadow_blur'),
    ]);
    return $filter_id;
  }
}

