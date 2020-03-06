<?php
/**
 * Copyright (C) 2018-2020 Graham Breach
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
 * Right-click (or long touch) context menu
 */
class ContextMenu {

  private $graph;
  private $js;

  private $function_added = false;
  private $callback;
  private $use_structure = false;
  private $namespace = '';

  /**
   * Constructor sets up options and root menu
   */
  public function __construct(&$graph, &$javascript)
  {
    if(!$graph->getOption('show_context_menu'))
      return;
    $this->graph =& $graph;
    $this->js =& $javascript;

    $this->callback = $graph->getOption('context_callback');
    $structure = $graph->getOption('structure');
    if(is_array($structure) && isset($structure['context_menu']))
      $this->use_structure = true;
    if($graph->getOption('namespace'))
      $this->namespace = 'svg:';

    $global = $graph->getOption('context_global');
    if($global !== false) {
      if($global === null)
        $global = [ [SVGGraph::VERSION, null] ];

      $entries = '';
      foreach($global as $entry) {
        $attr = ['name' => $entry[0], 'link' => $entry[1]];
        $entries .= $graph->element('svggraph:menuitem', $attr);
      }
      $menu = $graph->element('svggraph:menu', null, null, $entries);
      $xml = $graph->element('svggraph:data',
        ['xmlns:svggraph' => 'http://www.goat1000.com/svggraph'], null, $menu);
      $graph->defs->add($xml);
    }
  }

  /**
   * Adds the javascript function
   */
  public function addFunction()
  {
    $this->js->addFuncs('getE', 'finditem', 'newel', 'newtext',
      'svgNode', 'setattr', 'getData', 'svgCursorCoords');
    $this->js->addInitFunction('contextMenuInit');

    $opts = ['link_target', 'link_underline', 'stroke_width', 'round', 'font',
      'font_size', 'font_weight', 'document_menu', 'spacing', 'min_width',
      'shadow_opacity', 'mouseleave'];
    $colours = ['colour', 'link_colour', 'link_hover_colour', 'back_colour'];
    $vars = [];
    foreach($opts as $opt)
      $vars[$opt] = $this->graph->getOption('context_' . $opt);
    foreach($colours as $opt)
      $vars[$opt] = new Colour($this->graph, $this->graph->getOption('context_' . $opt));

    $svg_text = new Text($this->graph, $vars['font']);
    list(, $text_height) = $svg_text->measure('Test', $vars['font_size']);
    $text_baseline = $svg_text->baseline($vars['font_size']);

    $vars['pad_x'] = $this->graph->getOption('context_padding_x', 'context_padding');
    $vars['pad_y'] = $this->graph->getOption('context_padding_y', 'context_padding');
    $vars['text_start'] = $vars['pad_y'] + $text_baseline;
    $vars['rect_start'] = $vars['pad_y'] - $vars['spacing'] / 2;
    $vars['spacing'] += $text_height;

    $vars['round_part'] = $vars['mouseleave'] = $vars['underline_part'] = '';
    if($vars['link_underline'])
      $vars['underline_part'] = ", 'text-decoration': 'underline'";
    if($vars['round']) {
      $rnum = new Number($vars['round']);
      $vars['round_part'] = ', rx:"' . $rnum . 'px", ry:"' . $rnum . 'px"';
    }
    $cmoffs = 0;
    $half_stroke = $vars['stroke_width'] / 2;
    $vars['pad_x'] += $half_stroke;
    $vars['pad_y'] += $half_stroke;

    $vars['off_right'] = $vars['stroke_width'];
    $vars['off_bottom'] = $vars['stroke_width'];
    if(is_numeric($vars['shadow_opacity'])) {
      $cmoffs = 4;
      $vars['off_right'] += $cmoffs;
      $vars['off_bottom'] += $cmoffs;
    }
    $vars['cmoffs'] = $cmoffs;

    if($vars['document_menu']) {
      $this->js->insertFunction('rootContextMenu',
        "function rootContextMenu(){closeContextMenu();}\n");
    } else {
      $this->js->insertTemplate('rootContextMenu');
    }

    if((int)$vars['mouseleave'] > 0) {
      $mlnum = new Number($mouseleave);
      $vars['mouseleave'] = 'e[c].addEventListener("mouseleave",function(e) {' .
        'setTimeout(closeContextMenu,' . $mlnum . ');}, false);';
    }

    $vars['namespace'] = $this->namespace;
    $this->js->insertTemplate('contextMenu', $vars);
    $this->function_added = true;
  }

  /**
   * Adds context menu for item
   */
  public function setMenu(&$element, $dataset, &$item, $duplicate = false)
  {
    $menu = null;
    if(is_callable($this->callback)) {
      $menu = call_user_func($this->callback, $dataset, $item->key, $item->value);
    } elseif($this->use_structure) {
      $menu = $item->context_menu;
    }

    if(is_array($menu)) {
      if(!isset($element['id']))
        $element['id'] = $this->graph->newID();
      $var = json_encode($menu);
      $this->js->insertVariable('menus', $element['id'], $var, false);
      if($duplicate)
        $this->js->addOverlay($element['id'], $this->graph->newID());
    } else {
      // add a placeholder to make sure the variable exists
      $ignore_id = $this->graph->newID();
      $this->js->insertVariable('menus', $ignore_id, "''", false);
    }

    // set up menus after duplication
    if(!$this->function_added)
      $this->addFunction();
  }
}

