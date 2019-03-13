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
      if(is_null($global))
        $global = [ [SVGGraph::VERSION, null] ];

      $entries = '';
      foreach($global as $entry) {
        $entries .= '<svggraph:menuitem name="';
        $entries .= htmlspecialchars($entry[0], ENT_COMPAT, $graph->encoding);
        if(!is_null($entry[1])) {
          $entries .= '" link="';
          $entries .= htmlspecialchars($entry[1], ENT_COMPAT, $graph->encoding);
        }
        $entries .= '"/>' . "\n";
      }
      $xml = <<<XML
<svggraph:data xmlns:svggraph="http://www.goat1000.com/svggraph">
<svggraph:menu>
{$entries}</svggraph:menu>
</svggraph:data>
XML;
      $graph->addDefs($xml);
    }
  }

  /**
   * Adds the javascript function
   */
  public function addFunction()
  {
    $this->js->addFuncs('init', 'getE', 'finditem', 'newel', 'newtext',
      'svgNode', 'setattr', 'getData', 'svgCursorCoords');
    $this->js->insertVariable('initfns', null, 'contextMenuInit');

    $opts = ['colour', 'link_colour', 'link_hover_colour', 'link_target',
      'link_underline', 'stroke_width', 'round', 'font', 'font_size',
      'font_weight', 'document_menu', 'spacing', 'min_width',
      'shadow_opacity', 'mouseleave', 'back_colour'];
    foreach($opts as $opt)
      $$opt = $this->graph->getOption('context_' . $opt);

    $svg_text = new Text($this->graph, $font);
    list(, $text_height) = $svg_text->measure('Test', $font_size);
    $text_baseline = $svg_text->baseline($font_size);

    $pad_x = $this->graph->getOption('context_padding_x', 'context_padding');
    $pad_y = $this->graph->getOption('context_padding_y', 'context_padding');
    $back_colour = $this->graph->parseColour($back_colour);
    $text_start = $pad_y + $text_baseline;
    $rect_start = $pad_y - $spacing / 2;
    $spacing += $text_height;

    $underline_part = ($link_underline ? ", 'text-decoration': 'underline'" : '');
    $round_part = ($round ? ",rx:'{$round}px',ry:'{$round}px'" : "");
    $cmoffs = 0;
    $half_stroke = $stroke_width / 2;
    $pad_x += $half_stroke;
    $pad_y += $half_stroke;

    $off_right = $stroke_width;
    $off_bottom = $stroke_width;
    if(is_numeric($shadow_opacity)) {
      $cmoffs = 4;
      $off_right += $cmoffs;
      $off_bottom += $cmoffs;
    }

    $prevent_default = ($document_menu ? '' : 'e.preventDefault();');
    if($document_menu) {
      $root_menu = <<<JAVASCRIPT
      closeContextMenu();
JAVASCRIPT;
    } else {
      $root_menu = <<<JAVASCRIPT
      e.preventDefault();
      var de = svgNode(e), gm, rm, i, item, link;
      closeContextMenu();
      gm = getData(de, 'menu');
      if(gm) {
        rm = [];
        for(i = 0; i < gm.childNodes.length; ++i) {
          if(gm.childNodes[i].nodeName == 'svggraph:menuitem') {
            item = [gm.childNodes[i].getAttributeNS(null,'name')];
            link = gm.childNodes[i].getAttributeNS(null,'link');
            if(link)
              item.push(link);
            rm.push(item);
          }
        }
        setContextMenu(de,rm,e);
      }
JAVASCRIPT;
    }

    if((int)$mouseleave > 0) {
      $mouseleave = <<<JAVASCRIPT
    e[c].addEventListener('mouseleave', function(e) {
      setTimeout(closeContextMenu, {$mouseleave});
    }, false);
JAVASCRIPT;
    } else {
      $mouseleave = '';
    }

    $fn = <<<JAVASCRIPT
function closeContextMenu() {
  var g = getE('cMenu');
  g && g.parentNode.removeChild(g);
}
function setContextMenu(de,t,e) {
  var te, g, mh = 0, mw = {$min_width}, link, text, line = 0,
    bb, r, pos = svgCursorCoords(e), x = pos[0], y = pos[1], spacing = {$spacing},
    shadow, shadow_opacity = {$shadow_opacity}, target = '{$link_target}';
  g = newel('g', { id: 'cMenu', 'font-size': {$font_size}, 'font-family':
    '{$font}', 'font-weight': '{$font_weight}', fill:'{$colour}'});
  for(te in t) {
    text = newel('text', { x: '0px', y: '0px' });
    text.appendChild(newtext(t[te][0]));
    g.appendChild(text);
    de.appendChild(g);
    bb = text.getBBox();
    de.removeChild(g);
    g.removeChild(text);
    if(bb.width > mw)
      mw = bb.width;
  }
  for(te in t) {
    text = newel('text', { x: {$pad_x} + 'px', y: ({$text_start} + line * spacing) + 'px' });
    text.appendChild(newtext(t[te][0]));
    if(t[te][1]) {
      link = newel('a', { 'fill' : '{$link_colour}'{$underline_part} });
      link.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', t[te][1]);
      target && setattr(link, 'target', target);
      r = newel('rect', { x: {$pad_x} + 'px', y: ({$rect_start} + line * spacing) + 'px',
        width: mw + 'px', height: spacing + 'px', fill: '#000', opacity: 0});
      link.appendChild(r);
      link.appendChild(text);
      g.appendChild(link);
      link.addEventListener('mouseover', function(e) {
        setattr(this, 'fill', '{$link_hover_colour}');
        setattr(this.querySelector('rect'), 'opacity', 0.1);
      });
      link.addEventListener('mouseout', function(e) {
        setattr(this, 'fill', '{$link_colour}');
        setattr(this.querySelector('rect'), 'opacity', 0);
      });
    } else {
      g.appendChild(text);
    }
    ++line;
  }
  mw += {$pad_x} * 2;
  mh = (line * spacing) + {$pad_y} * 2;
  r = newel('rect', { x: '0px', y: '0px', width: mw + 'px', height: mh + 'px',
    'stroke-width': {$stroke_width} + 'px',
    fill: '{$back_colour}', stroke: '{$colour}'{$round_part}});
  g.insertBefore(r, g.childNodes[0]);
  x = Math.min(de.width.baseVal.value - mw - {$off_right},x);
  y = (de.height.baseVal.value - mh - {$off_bottom} < y ? y - mh : y);
  if(shadow_opacity > 0) {
    shadow = newel('rect',{ fill: '#000', opacity: {$shadow_opacity},
    'stroke-width': {$stroke_width} + 'px', stroke: '#000'{$round_part},
    x:'{$cmoffs}px',y:'{$cmoffs}px', width: mw + 'px', height: mh + 'px'});
    g.insertBefore(shadow, g.childNodes[0]);
  }
  setattr(g, 'transform', 'translate(' + x + ',' + y + ')');
  de.appendChild(g);
}
function contextMenuInit() {
  var c, e, nn = '{$this->namespace}svg';
  for(c in menus) {
    e = getE(c);
    e && e.addEventListener && e.addEventListener('contextmenu', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var t = finditem(e,menus), de = svgNode(e), g = getE('cMenu');
      g && g.parentNode.removeChild(g);
      setContextMenu(de,t,e);
      return false;
    },false);
  }
  e = document.querySelectorAll(nn);
  for(c = 0; c < e.length; ++c) {
    e[c].addEventListener('click', closeContextMenu, false);
{$mouseleave}
    e[c].addEventListener('keydown', function(e) {
      if(e.keyCode == 27)
        closeContextMenu();
    },false);
    e[c].addEventListener('contextmenu', function(e) {
{$root_menu}
    },false);
  }
}\n
JAVASCRIPT;
    $this->js->insertFunction('contextMenu', $fn);
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

