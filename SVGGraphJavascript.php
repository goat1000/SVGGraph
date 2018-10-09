<?php
/**
 * Copyright (C) 2012-2018 Graham Breach
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

class SVGGraphJavascript {

  private $settings;
  private $graph;
  protected $functions = array();
  protected $variables = array();
  protected $comments = array();
  protected $onload = FALSE;
  protected $fader_enabled = FALSE;
  protected $clickshow_enabled = FALSE;

  /**
   * Constructor takes array of settings and graph instance as arguments
   */
  public function __construct(&$settings, &$graph)
  {
    $this->settings = $settings;
    $this->graph = $graph;
  }

  /**
   * Return the settings as properties
   */
  public function __get($name)
  {
    $this->{$name} = isset($this->settings[$name]) ? $this->settings[$name] : null;
    return $this->{$name};
  }

  /**
   * Adds a javascript function
   */
  public function AddFunction($name, $realname = NULL)
  {
    if(is_null($realname))
      $realname = $name;

    if(isset($this->functions[$realname]))
      return TRUE;

    $simple_functions = array(
      'setattr' => "function setattr(i,a,v){i.setAttributeNS(null,a,v);return v}\n",
      'getE' => "function getE(i){return document.getElementById(i)}\n",
      'newtext' => "function newtext(c){return document.createTextNode(c)}\n",
    );

    if(isset($simple_functions[$name])) {
      $this->InsertFunction($name, $simple_functions[$name]);
      return;
    }

    $namespace = $this->namespace ? 'svg:' : '';

    switch($name)
    {
    // fadeIn, fadeOut are shortcuts to fader function
    case 'fadeIn' : $name = 'fader';
    case 'fadeOut' : $name = 'fader';
    case 'fader' :
      $this->AddFunction('getE');
      $this->AddFunction('setattr');
      $this->AddFunction('textAttr');
      $this->InsertVariable('faders', '', 1); // insert empty object
      $this->InsertVariable('fader_itimer', NULL);
      $fn = <<<JAVASCRIPT
function fadeIn(e,i,s){fader(e,i,0,1,s)}
function fadeOut(e,i,s){fader(e,i,1,0,s)}
function fader(e,i,o1,o2,s) {
  faders[i] = { id: i, o_start: o1, o_end: o2, step: (o1 < o2 ? s : -s) };
  fader_itimer || (fader_itimer = setInterval(fade,50));
}
function fade() {
  var f,ff,t,o,o1;
  for(f in faders) {
    ff = faders[f], t = getE(ff.id);
    if(t) {
      o1 = textAttr(t,'opacity');
      o = (o1 == '' ? ff.o_start : o1 * 1);
      o += ff.step;
      setattr(t,'opacity',o < .01 ? 0 : (o > .99 ? 1 : o));
      if((ff.step > 0 && o >= 1) || (ff.step < 0 && o <= 0))
        delete faders[f];
    }
  }
}\n
JAVASCRIPT;
      break;

    case 'newel' :
      $this->AddFunction('setattr');
      $fn = <<<JAVASCRIPT
function newel(e,a){
  var ns='http://www.w3.org/2000/svg', ne=document.createElementNS(ns,e),i;
  for(i in a)
    setattr(ne, i, a[i]);
  return ne;
}\n
JAVASCRIPT;
      break;
    case 'showhide' :
      $this->AddFunction('setattr');
      $fn = <<<JAVASCRIPT
function showhide(e,h){setattr(e,'visibility',h?'visible':'hidden');}\n
JAVASCRIPT;
      break;
    case 'finditem' :
      $fn = <<<JAVASCRIPT
function finditem(e,list) {
  var l = e.target.correspondingUseElement || e.target, t;
  while(!t && l.parentNode) {
    t = l.id && list[l.id]
    l = l.parentNode;
  }
  return t;
}\n
JAVASCRIPT;
      break;
    case 'contextMenu' :
      $this->AddFunction('init');
      $this->AddFunction('getE');
      $this->AddFunction('finditem');
      $this->AddFunction('newel');
      $this->AddFunction('newtext');
      $this->AddFunction('svgNode');
      $this->AddFunction('setattr');
      $this->AddFunction('getData');
      $this->AddFunction('svgCursorCoords');
      $this->InsertVariable('initfns', NULL, 'contextMenuInit');

      $colour = $this->graph->GetOption('context_colour');
      $back_colour = $this->graph->ParseColour(
        $this->graph->GetOption('context_back_colour'));
      $link_colour = $this->graph->GetOption('context_link_colour');
      $link_hover_colour = $this->graph->GetOption('context_link_hover_colour');
      $link_target = $this->graph->GetOption('context_link_target');
      $link_underline = $this->graph->GetOption('context_link_underline');
      $stroke_width = $this->graph->GetOption('context_stroke_width');
      $round = $this->graph->GetOption('context_round');
      $font = $this->graph->GetOption('context_font');
      $font_size = $this->graph->GetOption('context_font_size');
      $font_weight = $this->graph->GetOption('context_font_weight');
      $doc_menu = $this->graph->GetOption('context_document_menu');

      $svg_text = new SVGGraphText($this->graph, $font);
      list($text_x, $text_height) = $svg_text->Measure('Test', $font_size);
      $text_baseline = $svg_text->Baseline($font_size);

      $min_w = $this->graph->GetOption('context_min_width');
      $pad_x = $this->graph->GetOption('context_padding_x', 'context_padding');
      $pad_y = $this->graph->GetOption('context_padding_y', 'context_padding');
      $spacing = $this->graph->GetOption('context_spacing');
      $text_start = $pad_y + $text_baseline;
      $rect_start = $pad_y - $spacing / 2;
      $spacing += $text_height;

      $underline_part = ($link_underline ? ", 'text-decoration': 'underline'" : '');
      $round_part = ($round ? ",rx:'{$round}px',ry:'{$round}px'" : "");
      $shadow_opacity = $this->graph->GetOption('context_shadow_opacity');
      $cmoffs = 0;
      $half_stroke = $stroke_width / 2;
      $pad_x += $half_stroke;
      $pad_y += $half_stroke;

      $off_right = $this->context_stroke_width;
      $off_bottom = $this->context_stroke_width;
      if(is_numeric($shadow_opacity)) {
        $cmoffs = 4;
        $off_right += $cmoffs;
        $off_bottom += $cmoffs;
      }

      $prevent_default = ($doc_menu ? '' : 'e.preventDefault();');
      if($doc_menu) {
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

      $mouseleave_delay = (int)$this->graph->GetOption('context_mouseleave');
      if($mouseleave_delay > 0) {
        $mouseleave = <<<JAVASCRIPT
    e[c].addEventListener('mouseleave', function(e) {
      setTimeout(closeContextMenu, {$mouseleave_delay});
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
  var te, g, mh = 0, mw = {$min_w}, link, text, line = 0,
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
  var c, e, nn = '{$namespace}svg';
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
      break;
    case 'tooltip' :
      $this->AddFunction('getE');
      $this->AddFunction('setattr');
      $this->AddFunction('newel');
      $this->AddFunction('showhide');
      $this->AddFunction('svgNode');
      $this->AddFunction('svgCursorCoords');
      $this->InsertVariable('tooltipOn', '');
      $max_x = $this->graph->width - $this->tooltip_stroke_width;
      $max_y = $this->graph->height - $this->tooltip_stroke_width;
      if(is_numeric($this->tooltip_shadow_opacity)) {
        $ttoffs = (2 - $this->tooltip_stroke_width/2);
        $max_x -= $ttoffs;
        $max_y -= $ttoffs;
        $shadow = <<<JAVASCRIPT
    shadow = newel('rect',{
      fill: '#000',
      opacity: {$this->tooltip_shadow_opacity},
      x:'{$ttoffs}px',y:'{$ttoffs}px',
      width:'10px',height:'10px',
      id: 'ttshdw',
      rx:'{$this->tooltip_round}px',ry:'{$this->tooltip_round}px'
    });
    tt.appendChild(shadow);
JAVASCRIPT;
      } else {
        $shadow = '';
      }
      $dpad = 2 * $this->tooltip_padding;
      $back_colour = $this->graph->ParseColour($this->tooltip_back_colour);
      $fn = <<<JAVASCRIPT
function tooltip(e,callback,on,param) {
  var tt = getE('tooltip'), rect = getE('ttrect'), shadow = getE('ttshdw'),
    offset = {$this->tooltip_offset}, pos = svgCursorCoords(e),
    x = pos[0] + offset, y = pos[1] + offset, inner, brect, bw, bh,
    sw, sh,
    de = svgNode(e);
  if(on && !tt) {
    tt = newel('g',{id:'tooltip',visibility:'visible'});
    rect = newel('rect',{
      stroke: '{$this->tooltip_colour}',
      'stroke-width': '{$this->tooltip_stroke_width}px',
      fill: '{$back_colour}',
      width:'10px',height:'10px',
      id: 'ttrect',
      rx:'{$this->tooltip_round}px',ry:'{$this->tooltip_round}px'
    });
{$shadow}
    tt.appendChild(rect);
  }
  if(tt) {
    if(on) {
      if(tt.parentNode && tt.parentNode != de)
        tt.parentNode.removeChild(tt);
      de.appendChild(tt);
    }
    showhide(tt,on);
  }
  inner = callback(e,tt,on,param);
  if(inner && on) {
    brect = inner.getBBox();
    bw = Math.ceil(brect.width + {$dpad});
    bh = Math.ceil(brect.height + {$dpad});
    setattr(rect, 'width', bw + 'px');
    setattr(rect, 'height', bh + 'px');
    setattr(inner, 'transform', 'translate(' + (bw / 2) + ',0)');
    if(shadow) {
      setattr(shadow, 'width', (bw + {$this->tooltip_stroke_width}) + 'px');
      setattr(shadow, 'height', (bh + {$this->tooltip_stroke_width}) + 'px');
    }
    if(bw + x > {$max_x}) {
      x -= bw + offset * 2;
      x = Math.max(x, 0);
    }
    if(bh + y > {$max_y}) {
      y -= bh + offset * 2;
      y = Math.max(y, 0);
    }
  }
  on && setattr(tt,'transform','translate('+x+' '+y+')');
  tooltipOn = on ? 1 : 0;
}\n
JAVASCRIPT;
      break;

    case 'texttt' :
      $this->AddFunction('getE');
      $this->AddFunction('setattr');
      $this->AddFunction('newel');
      $this->AddFunction('newtext');
      $tty = $this->tooltip_font_size + $this->tooltip_padding;
      $ttypx = "{$tty}px";
      $fn = <<<JAVASCRIPT
function texttt(e,tt,on,t){
  var ttt = getE('tooltiptext'), lines, i, ts, xpos;
  if(on) {
    lines = t.split('\\\\n');
    xpos = '{$this->tooltip_padding}px';
    if(!ttt) {
      ttt = newel('g', {
        id: 'tooltiptext',
        fill: '{$this->tooltip_colour}',
        'font-size': '{$this->tooltip_font_size}px',
        'font-family': '{$this->tooltip_font}',
        'font-weight': '{$this->tooltip_font_weight}',
        'text-anchor': 'middle'
      });
      tt.appendChild(ttt);
    }
    while(ttt.childNodes.length > 0)
      ttt.removeChild(ttt.childNodes[0]);
    for(i = 0; i < lines.length; ++i) {
      ts = newel('text', { y: ({$tty} * (i + 1)) + 'px' });
      ts.appendChild(newtext(lines[i]));
      ttt.appendChild(ts);
    }
  }
  ttt && showhide(ttt,on);
  return ttt;
}\n
JAVASCRIPT;
      break;
    case 'ttEvent' :
      $this->AddFunction('finditem');
      $this->AddFunction('init');
      $this->InsertVariable('initfns', NULL, 'ttEvt');
      $fn = <<<JAVASCRIPT
function ttEvt() {
  document.addEventListener && document.addEventListener('mousemove',
    function(e) {
      var t = finditem(e,tips);
      if(t || tooltipOn)
        tooltip(e,texttt,t,t);
    },false);
}\n
JAVASCRIPT;
      break;
    case 'popFront' :
      $this->AddFunction('getE');
      $this->AddFunction('init');
      $this->AddFunction('finditem');
      $this->InsertVariable('initfns', NULL, 'popFrontInit');
      $fn = <<<JAVASCRIPT
function popFrontInit() {
  var c, e;
  for(c in popfronts) {
    e = getE(c);
    e.addEventListener && e.addEventListener('mousemove', function(e) {
      var t = finditem(e,popfronts), te, p;
      if(t) {
        te = getE(t.id);
        if(te) {
          p = te.parentNode;
          p.removeChild(te);
          p.appendChild(te);
        }
      }
    },false);
  }
}\n
JAVASCRIPT;
      break;
    case 'fading' :
      $fn = <<<JAVASCRIPT
function fading(id) {
  var c;
  for(c in fades) {
    if(fades[c].id == id)
      return true;
  }
  return false;
}\n
JAVASCRIPT;
      break;
    case 'clickShowEvent' :
      if($this->fader_enabled)
        return $this->FadeAndClick();

      $this->AddFunction('getE');
      $this->AddFunction('init');
      $this->AddFunction('finditem');
      $this->AddFunction('setattr');
      $this->InsertVariable('initfns', NULL, 'clickShowInit');
      $fn = <<<JAVASCRIPT
function clickShowInit() {
  var c, e;
  for(c in clickElements) {
    e = getE(c);
    e.addEventListener && e.addEventListener('click', function(e) {
      var t = finditem(e,clickElements), te;
      if(t) {
        te = getE(t);
        clickMap[t] = !clickMap[t];
        te && setattr(te,'opacity',clickMap[t] ? 1 : 0);
      }
    },false);
  }
}\n
JAVASCRIPT;
      break;
    case 'fadeEvent' :
      if($this->clickshow_enabled)
        return $this->FadeAndClick();

      $this->AddFunction('getE');
      $this->AddFunction('init');
      $this->AddFunction('setattr');
      $this->AddFunction('textAttr');
      $this->InsertVariable('initfns', NULL, 'fade');
      $fn = <<<JAVASCRIPT
function fade() {
  var f,f1,e,o;
  for(f in fades) {
    f1 = fades[f];
    if(f1.dir) {
      e = getE(f1.id);
      if(e) {
        o = (textAttr(e,'opacity') || fstart) * 1 + f1.dir;
        setattr(e,'opacity', o < .01 ? 0 : (o > .99 ? 1 : o));
      }
    }
  }
  setTimeout(fade,50);
}\n
JAVASCRIPT;
      break;
    case 'fadeEventIn' :
      $this->AddFunction('init');
      $this->AddFunction('finditem');
      $this->InsertVariable('initfns', NULL, 'fiEvt');
      $fn = <<<JAVASCRIPT
function fiEvt() {
  var f;
  document.addEventListener && document.addEventListener('mouseover',
    function(e) {
      var t = finditem(e,fades);
      t && (t.dir = fistep);
    },false);
}\n
JAVASCRIPT;
      break;
    case 'fadeEventOut' :
      $this->AddFunction('init');
      $this->AddFunction('finditem');
      $this->InsertVariable('initfns', NULL, 'foEvt');
      $fn = <<<JAVASCRIPT
function foEvt() {
  document.addEventListener && document.addEventListener('mouseout',
    function(e) {
      var t = finditem(e,fades);
      t && (t.dir = fostep);
    },false);
}\n
JAVASCRIPT;
      break;
    case 'duplicate' :
      $this->AddFunction('getE');
      $this->AddFunction('newel');
      $this->AddFunction('init');
      $this->AddFunction('setattr');
      $this->InsertVariable('initfns', NULL, 'initDups');
      $fn = <<<JAVASCRIPT
function duplicate(f,t) {
  var e = getE(f), g, a, p = e && e.parentNode, m;
  if(e) {
    while(p.parentNode && p.nodeName != '{$namespace}svg' &&
      (p.nodeName != '{$namespace}g' || !p.getAttributeNS(null,'clip-path'))) {
      p.nodeName == '{$namespace}a' && (a = p);
      p = p.parentNode;
    }
    g = e.cloneNode(true);
    setattr(g,'opacity',0);
    e.id = t;

    if(a) {
      a = a.cloneNode(false);
      a.appendChild(g);
      g = a;
    }
    p.appendChild(g);
  }
}
function initDups() {
  for(var d in dups)
    duplicate(d,dups[d]);
}\n
JAVASCRIPT;
      break;
    case 'svgNode' :
      $fn = <<<JAVASCRIPT
function svgNode(e) {
  var d = e.target.correspondingUseElement || e.target, nn = '{$namespace}svg';
  while(d.parentNode && d.nodeName != nn)
    d = d.parentNode;
  return d.nodeName == nn ? d : null;
}\n
JAVASCRIPT;
      break;
    case 'svgCursorCoords' :
      $this->AddFunction('svgNode');
      $fn = <<<JAVASCRIPT
function svgCursorCoords(e) {
  var d = svgNode(e), pt;
  if(!d || !d.createSVGPoint || !d.getScreenCTM) {
    return [e.clientX,e.clientY];
  }
  pt = d.createSVGPoint(); pt.x = e.clientX; pt.y = e.clientY;
  pt = pt.matrixTransform(d.getScreenCTM().inverse());
  return [pt.x,pt.y];
}\n
JAVASCRIPT;
      break;
    case 'autoHide' :
      $this->AddFunction('init');
      $this->AddFunction('getE');
      $this->AddFunction('setattr');
      $this->AddFunction('finditem');
      $this->InsertVariable('initfns', NULL, 'autoHide');
      $fn = <<<JAVASCRIPT
function autoHide() {
  if(document.addEventListener) {
    for(var a in autohide)
      autohide[a] = getE(a);
    document.addEventListener('mouseout', function(e) {
      var t = finditem(e,autohide);
      t && setattr(t,'opacity',1);
    });
    document.addEventListener('mouseover', function(e) {
      var t = finditem(e,autohide);
      t && setattr(t,'opacity',0);
    });
  }
}\n
JAVASCRIPT;
      break;
    case 'chEvt' :
      $this->AddFunction('init');
      $this->InsertVariable('initfns', NULL, 'chEvt');
      $fn = <<<JAVASCRIPT
function chEvt() {
  if(document.addEventListener) {
    document.addEventListener('mousemove', crosshairs, false);
    document.addEventListener('mouseout', crosshairs, false);
  }
}\n
JAVASCRIPT;
      break;
    case 'getData' :
      $fn = <<<JAVASCRIPT
function getData(doc,ename) {
  var ns = 'http://www.goat1000.com/svggraph', element;
  element = doc.getElementsByTagName('svggraph:' + ename);
  if(!element.length)
    element = doc.getElementsByTagNameNS(ns, ename);
  if(!element.length)
    return null;
  return element[0];
}\n
JAVASCRIPT;
      break;
    case 'fitRect' :
      $this->AddFunction('setattr');
      $fn = <<<JAVASCRIPT
function fitRect(rect,brect,pad) {
  var bw = Math.ceil(brect.width + pad + pad),
    bh = Math.ceil(brect.height + pad + pad);
  setattr(rect, 'x', (brect.x - pad) + 'px');
  setattr(rect, 'y', (brect.y - pad) + 'px');
  setattr(rect, 'width', bw + 'px');
  setattr(rect, 'height', bh + 'px');
}\n
JAVASCRIPT;
      break;
    case 'textAttr' :
      $fn = <<<JAVASCRIPT
function textAttr(e,a) {
  var s = e.getAttributeNS(null,a);
  return s ? s : '';
}\n
JAVASCRIPT;
      break;

    case 'strValueX' :
      $fn = <<<JAVASCRIPT
function strValueX(de,x,w,g,ub,ua) {
  var z = g.getAttributeNS(null, 'zero'), s = g.getAttributeNS(null, 'scale'),
    p = g.getAttributeNS(null, 'precision');
  return ub + ((x - z) / s).toFixed(p) + ua;
}\n
JAVASCRIPT;
      break;
    case 'strValueY' :
      $fn = <<<JAVASCRIPT
function strValueY(de,y,h,g,ub,ua) {
  var z = g.getAttributeNS(null, 'zero'), s = g.getAttributeNS(null, 'scale'),
    p = g.getAttributeNS(null, 'precision');
  return ub + ((y - z) / s).toFixed(p) + ua;
}\n
JAVASCRIPT;
      break;
    case 'logStrValueX' :
      $fn = <<<JAVASCRIPT
function logStrValueX(de,x,w,g,ub,ua) {
  var z = g.getAttributeNS(null, 'zero'), s = g.getAttributeNS(null, 'scale'),
    p = g.getAttributeNS(null, 'precision'), b = g.getAttributeNS(null, 'base'),
    lgmin, lgmax, lgmul;
    lgmin = Math.log(z)/Math.log(b);
    lgmax = Math.log(s)/Math.log(b);
    lgmul = w / (lgmax - lgmin);
  return ub + (Math.pow(b, lgmin*1 + x / lgmul)).toFixed(p) + ua;
}\n
JAVASCRIPT;
      break;
    case 'logStrValueY' :
      $fn = <<<JAVASCRIPT
function logStrValueY(de,y,h,g,ub,ua) {
  var z = g.getAttributeNS(null, 'zero'), s = g.getAttributeNS(null, 'scale'),
    p = g.getAttributeNS(null, 'precision'), b = g.getAttributeNS(null, 'base'),
    lgmin, lgmax, lgmul;
    lgmin = Math.log(z)/Math.log(b);
    lgmax = Math.log(s)/Math.log(b);
    lgmul = h / (lgmax - lgmin);
  return ub + (Math.pow(b, lgmin*1 + y / lgmul)).toFixed(p) + ua;
}\n
JAVASCRIPT;
      break;
    case 'kround' :
      // round to nearest whole number
      $fn = "function kround(v){return Math.round(v)|0;}\n";
      break;
    case 'kroundDown' :
      // floor function
      $fn = "function kroundDown(v){return v|0;}\n";
      break;
    case 'keyStrValueX' :
      $fn = <<<JAVASCRIPT
function keyStrValueX(de,x,w,g,ub,ua) {
  var z = g.getAttributeNS(null, 'zero'), s = g.getAttributeNS(null, 'scale'),
    p = g.getAttributeNS(null, 'precision'), keys = getData(de, 'keys'),
    rfnc = g.getAttributeNS(null, 'round'), str = '', n = 0, i = 0,
    v = window[rfnc]((x - z) / s);
  if(keys) {
    while(i <= v && n < keys.childNodes.length) {
      if(keys.childNodes[n].nodeName == 'svggraph:key') {
        if(i == v)
          str = keys.childNodes[n].getAttributeNS(null,'value');
        ++i;
      }
      ++n;
    }
  }
  return str;
}\n
JAVASCRIPT;
      break;
    case 'keyStrValueY' :
      $fn = <<<JAVASCRIPT
function keyStrValueY(de,y,h,g,ub,ua) {
  var z = g.getAttributeNS(null, 'zero'), s = g.getAttributeNS(null, 'scale'),
    p = g.getAttributeNS(null, 'precision'), keys = getData(de, 'keys'),
    rfnc = g.getAttributeNS(null, 'round'), str = '', n = 0, i = 0,
    v = window[rfnc]((y - z) / s);
  if(keys) {
    while(i <= v && n < keys.childNodes.length) {
      if(keys.childNodes[n].nodeName == 'svggraph:key') {
        if(i == v)
          str = keys.childNodes[n].getAttributeNS(null,'value');
        ++i;
      }
      ++n;
    }
  }
  return str;
}\n
JAVASCRIPT;
      break;

    case 'dateFormat' :
      $fn = <<<JAVASCRIPT
function dateFormat(d,f) {
  var str = '', i, s, m = ['January','February','March','April','May','June',
    'July','August','September','October','November','December'],
    w = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  for(i = 0; i < f.length; ++i) {
    switch(f[i]) {
    case 'Y' : s = d.getUTCFullYear(); break;
    case 'y' : s = (d.getUTCFullYear() + '').substr(2); break;
    case 'F' : s = m[d.getUTCMonth()]; break;
    case 'M' : s = m[d.getUTCMonth()].substr(0,3); break;
    case 'm' : s = ('0' + (d.getUTCMonth() + 1)).substr(-2); break;
    case 'n' : s = d.getUTCMonth() + 1; break;
    case 'd' : s = ('0' + d.getUTCDate()).substr(-2); break;
    case 'D' : s = w[d.getUTCDay()].substr(0,3); break;
    case 'l' : s = w[d.getUTCDay()]; break;
    case 'a' : s = ['am','pm'][d.getUTCHours() > 11 ? 1 : 0]; break;
    case 'A' : s = ['AM','PM'][d.getUTCHours() > 11 ? 1 : 0]; break;
    case 'g' : s = d.getUTCHours() % 12 || 12; break;
    case 'G' : s = d.getUTCHours(); break;
    case 'h' : s = ('0' + (d.getUTCHours() % 12 || 12)).substr(-2); break;
    case 'H' : s = ('0' + d.getUTCHours()).substr(-2); break;
    case 'i' : s = ('0' + d.getUTCMinutes()).substr(-2); break;
    case 's' : s = ('0' + d.getUTCSeconds()).substr(-2); break;
    default:
      s = f[i];
    }
    str += s;
  }
  return str;
}\n
JAVASCRIPT;
      break;
    case 'dateStrValueX' :
      $this->AddFunction('dateFormat');
      $fn = <<<JAVASCRIPT
function dateStrValueX(de,x,w,g,ub,ua) {
  var z = new Date(g.getAttributeNS(null, 'zero')),
    s = g.getAttributeNS(null, 'scale'), fmt = g.getAttributeNS(null, 'format'),
    dt = new Date(z.valueOf() + (1000 * x * s)), str = '';
  str = dateFormat(dt,fmt);
  return str;
}\n
JAVASCRIPT;
      break;
    case 'dateStrValueY' :
      $this->AddFunction('dateFormat');
      $fn = <<<JAVASCRIPT
function dateStrValueY(de,y,h,g,ub,ua) {
  var z = new Date(g.getAttributeNS(null, 'zero')),
    s = g.getAttributeNS(null, 'scale'), fmt = g.getAttributeNS(null, 'format'),
    dt = new Date(z.valueOf() + (1000 * y * s)), str = '';
  str = dateFormat(dt,fmt);
  return str;
}\n
JAVASCRIPT;
      break;

    case 'showCoords' :
      $this->AddFunction('getE');
      $this->AddFunction('newel');
      $this->AddFunction('newtext');
      $this->AddFunction('getData');
      $this->AddFunction('showhide');
      $this->AddFunction('fitRect');
      $this->AddFunction('textAttr');

      // add the default x/y coord functions if required
      $this->AddFunction('strValueX');
      $this->AddFunction('strValueY');

      // format text for assoc X, assoc Y or x,y
      $yb = "textAttr(ti,'unitsby')";
      $ya = "textAttr(ti,'unitsy')";
      $xb = "textAttr(ti,'unitsbx')";
      $xa = "textAttr(ti,'unitsx')";
      $text_format_x = "window[fnx](de,x,bb.width,gx,{$xb},{$xa})";
      $text_format_y = "window[fny](de,bb.height-y,bb.height,gy,{$yb},{$ya})";

      if(!$this->crosshairs_show_h)
        $text_format = $text_format_x;
      elseif(!$this->crosshairs_show_v)
        $text_format = $text_format_y;
      else
        $text_format = "{$text_format_x} + ', ' + {$text_format_y}";

      $font_size = max(3, (int)$this->crosshairs_text_font_size);
      $pad = max(0, (int)$this->crosshairs_text_padding);
      $space = max(0, (int)$this->crosshairs_text_space);
      // calculate these here to save doing it in JS
      $pad_space = $pad + $space;
      $space2 = $space * 2;
      $fn = <<<JAVASCRIPT
function showCoords(de,x,y,bb,on) {
  var gx = getData(de, 'gridx'), gy = getData(de, 'gridy'),
    textList = getData(de,'chtext'), group, i, x1, y1,
    fnx = gx.getAttributeNS(null, 'function'),
    fny = gy.getAttributeNS(null, 'function'), textNode, rect, tbb, ti, ds;
  for(i = 0; i < textList.childNodes.length; ++i) {
    if(textList.childNodes[i].nodeName == 'svggraph:chtextitem') {
      ti = textList.childNodes[i];
      group = getE(ti.getAttributeNS(null, 'groupid'));
      if(on) {
        textNode = group.querySelector('text');
        rect = group.querySelector('rect');
        while(textNode.childNodes.length > 0)
          textNode.removeChild(textNode.childNodes[0]);
        textNode.appendChild(newtext({$text_format}));
        setattr(textNode, 'y', 0 + 'px');
        tbb = textNode.getBBox();
        ds = tbb.height + tbb.y;
        x1 = x + bb.x + {$pad_space};
        y1 = y + bb.y - {$pad_space} - ds;
        if(x1 + tbb.width + {$pad} > bb.x + bb.width)
          x1 -= group.getBBox().width + {$space2};
        if(y1 - tbb.height - {$pad} < bb.y)
          y1 = y + bb.y + tbb.height + {$pad_space} - ds;
        setattr(textNode, 'x', x1 + 'px');
        setattr(textNode, 'y', y1 + 'px');
        tbb = textNode.getBBox();
        fitRect(rect,tbb,{$pad});
      }
      showhide(group, on);
    }
  }
}\n
JAVASCRIPT;
      break;
    case 'crosshairs' :
      $this->AddFunction('chEvt');
      $this->AddFunction('setattr');
      $this->AddFunction('svgNode');
      $this->AddFunction('svgCursorCoords');
      $this->AddFunction('showhide');
      $show_text = '';
      if($this->crosshairs_show_text) {
        $this->AddFunction('showCoords');
        $show_text = "showCoords(de, x - bb.x, y - bb.y, bb, on);";
      }
      $show_x = $this->crosshairs_show_h ? 'showhide(xc, on);' : '';
      $show_y = $this->crosshairs_show_v ? 'showhide(yc, on);' : '';
      $fn = <<<JAVASCRIPT
function crosshairs(e) {
  var de = svgNode(e), pos = svgCursorCoords(e), xc, yc, grid, bb, on, x, y;
  if(!de)
    return;
  xc = de.querySelector('.chX');
  yc = de.querySelector('.chY');
  grid = de.querySelector('.grid');
  if(!grid)
    return;
  bb = grid.getBBox();
  x = pos[0];
  y = pos[1];
  on = (x >= bb.x && x <= bb.x + bb.width && y >= bb.y && y <= bb.y + bb.height);
  if(on) {
    setattr(xc,'y1',setattr(xc,'y2', y));
    setattr(yc,'x1',setattr(yc,'x2', x));
  }
  {$show_text}
  {$show_x}
  {$show_y}
}\n
JAVASCRIPT;
      break;
    case 'dragOver' :
      $this->AddFunction('getE');
      $this->AddFunction('svgCursorCoords');
      $this->AddFunction('setattr');
      $fn = <<<JAVASCRIPT
function dragOver(e,el) {
  var t = getE(el), d;
  if(t && t.dragging) {
    d = t.draginfo;
    var pos = svgCursorCoords(e);
    d[2] = d[2] - d[0] + pos[0];
    d[3] = d[3] - d[1] + pos[1];
    d[0] = pos[0];
    d[1] = pos[1];
    setattr(d[4], 'transform', 'translate(' + d[2] + ',' + d[3] + ')');
    return false;
  }
}\n
JAVASCRIPT;
      break;
    case 'dragStart' :
      $this->AddFunction('getE');
      $this->AddFunction('newel');
      $fn = <<<JAVASCRIPT
function dragStart(e,el) {
  var t = getE(el), m;
  var pos = svgCursorCoords(e);
  if(!t.draginfo) {
    t.draginfo = [0,0,0,0,newel('g',{cursor:'move'})];
    t.parentNode.appendChild(t.draginfo[4]);
    t.parentNode.removeChild(t);
    t.draginfo[4].appendChild(t);
  }
  t.draginfo[0] = pos[0];
  t.draginfo[1] = pos[1];

  t.dragging = 1;
  return false;
}\n
JAVASCRIPT;
      break;
    case 'dragEnd' :
      $this->AddFunction('getE');
      $fn = <<<JAVASCRIPT
function dragEnd(e,el) {
  getE(el).dragging = null;
}\n
JAVASCRIPT;
      break;
    case 'dragEvent' :
      $this->AddFunction('init');
      $this->AddFunction('newel');
      $this->AddFunction('getE');
      $this->AddFunction('setattr');
      $this->AddFunction('finditem');
      $this->AddFunction('svgCursorCoords');
      $this->InsertVariable('initfns', NULL, 'initDrag');
      $fn = <<<JAVASCRIPT
function initDrag() {
  var d, e;
  if(document.addEventListener) {
    for(d in draggable) {
      e = draggable[d] = getE(d);
      e.draginfo = [0,0,0,0,newel('g',{cursor:'move'})];
      (e.nearestViewportElement || document.documentElement).appendChild(e.draginfo[4]);
      e.parentNode.removeChild(e);
      e.draginfo[4].appendChild(e);
    }
    document.addEventListener('mouseup', function(e) {
      var t = finditem(e,draggable);
      if(t && t.dragging) {
        t.dragging = null;
      }
    });
    document.addEventListener('mousedown', function(e) {
      var t = finditem(e,draggable), m;
      if(t && !t.dragging) {
        var pos = svgCursorCoords(e);
        t.draginfo[0] = pos[0];
        t.draginfo[1] = pos[1];
        t.dragging = 1;
        e.cancelBubble = true;
        e.preventDefault && e.preventDefault();
        return false;
      }
    });
    function dragmove(e) {
      var t = finditem(e,draggable), d;
      if(t && t.dragging) {
        d = t.draginfo;
        var pos = svgCursorCoords(e);
        d[2] = d[2] - d[0] + pos[0];
        d[3] = d[3] - d[1] + pos[1];
        d[0] = pos[0];
        d[1] = pos[1];
        setattr(d[4], 'transform', 'translate(' + d[2] + ',' + d[3] + ')');
        e.cancelBubble = true;
        e.preventDefault && e.preventDefault();
        return false;
      }
    };
    document.addEventListener('mousemove', dragmove);
    document.addEventListener('mouseout', dragmove);
  }
}\n
JAVASCRIPT;
      break;
    case 'init' :
      $this->onload = TRUE;
      $fn = <<<JAVASCRIPT
function init() {
  if(!document.addEventListener || !initfns)
    return;
  for(var f in initfns)
    eval(initfns[f] + '()');
  initfns = [];
}\n
JAVASCRIPT;
      break;

    default :
      // Trying to add a function that doesn't exist?
      throw new Exception("Unknown function '$name'");
    }

    $this->InsertFunction($realname, $fn);
  }

  /**
   * Inserts a Javascript function into the list
   */
  public function InsertFunction($name, $fn)
  {
    $this->functions[$name] = $fn;
  }

  /**
   * Convert hex from regex matched entity to javascript escape sequence
   */
  public static function hex2js($m)
  {
    return sprintf('\u%04x', base_convert($m[1], 16, 10));
  }

  /**
   * Convert decimal from regex matched entity to javascript escape sequence
   */
  public static function dec2js($m)
  {
    return sprintf('\u%04x', $m[1]);
  }

  public static function ReEscape($string)
  {
    // convert XML char entities to JS unicode
    $string = preg_replace_callback('/&#x([a-f0-9]+);/',
      'SVGGraphJavascript::hex2js', $string);
    $string = preg_replace_callback('/&#([0-9]+);/',
      'SVGGraphJavascript::dec2js', $string);
    return $string;
  }

  /**
   * Adds a Javascript variable
   * - use $value:$more for assoc
   * - use NULL:$more for array
   */
  public function InsertVariable($var, $value, $more = NULL, $quote = TRUE)
  {
    $q = $quote ? "'" : '';
    if(is_null($more))
      $this->variables[$var] = $q . $this->ReEscape($value) . $q;
    elseif(is_null($value))
      $this->variables[$var][] = $q . $this->ReEscape($more) . $q;
    else
      $this->variables[$var][$value] = $q . $this->ReEscape($more) . $q;
  }

  /**
   * Insert a comment into the Javascript section - handy for debugging!
   */
  public function InsertComment($details)
  {
    $this->comments[] = $details;
  }

  /**
   * Adds an inline event handler to an element's array
   */
  public function AddEventHandler(&$array, $evt, $code)
  {
    if(isset($array[$evt]))
      $array[$evt] .= ';' . $code;
    else
      $array[$evt] = $code;
  }

  /**
   * Fade and click at the same time requires different functions
   */
  private function FadeAndClick()
  {
    $this->AddFunction('getE');
    $this->AddFunction('init');
    $this->AddFunction('finditem');
    $this->AddFunction('fading');
    $this->AddFunction('textAttr');
    $this->AddFunction('setattr');
    $this->InsertVariable('initfns', NULL, 'clickShowInit');
    $this->InsertVariable('initfns', NULL, 'fade');
    $this->variables['initfns'] = array_unique($this->variables['initfns']);

    $fn = <<<JAVASCRIPT
function clickShowInit() {
  var c, e;
  for(c in clickElements) {
    e = getE(c);
    e.addEventListener && e.addEventListener('click', function(e) {
      var t = finditem(e,clickElements), te;
      if(t) {
        clickMap[t] = !clickMap[t];
        if(!(fading(t))) {
          te = getE(t);
          te && setattr(te,'opacity',clickMap[t] ? 1 : 0);
        }
      }
    },false);
  }
}\n
JAVASCRIPT;
    $this->InsertFunction('clickShowEvent', $fn);

      $fn = <<<JAVASCRIPT
function fade() {
  var f,f1,e,o;
  for(f in fades) {
    f1 = fades[f];
    if(!(clickElements[f] && clickMap[clickElements[f]]) && f1.dir) {
      e = getE(f1.id);
      if(e) {
        o = (textAttr(e,'opacity') || fstart) * 1 + f1.dir;
        setattr(e,'opacity', o < .01 ? 0 : (o > .99 ? 1 : o));
      }
    }
  }
  setTimeout(fade,50);
}\n
JAVASCRIPT;
    $this->InsertFunction('fadeEvent', $fn);
  }

  /**
   * Sets the tooltip for an element
   */
  public function SetTooltip(&$element, $text, $duplicate = FALSE)
  {
    $this->AddFunction('tooltip');
    $this->AddFunction('texttt');
    if($this->compat_events) {
      $this->AddEventHandler($element, 'onmousemove',
        "tooltip(evt,texttt,true,'$text')");
      $this->AddEventHandler($element, 'onmouseout',
        "tooltip(evt,texttt,false,'')");
    } else {
      if(!isset($element['id']))
        $element['id'] = $this->graph->NewID();
      $this->AddFunction('ttEvent');
      $this->InsertVariable('tips', $element['id'], $text);
    }
    if($duplicate) {
      if(!isset($element['id']))
        $element['id'] = $this->graph->NewID();
      $this->AddOverlay($element['id'], $this->graph->NewID());
    }
  }

  /**
   * Sets the context menu for an element
   */
  public function SetContextMenu(&$element, $menu, $duplicate = FALSE)
  {
    if(is_array($menu)) {
      if(!isset($element['id']))
        $element['id'] = $this->graph->NewID();
      $var = json_encode($menu);
      $this->InsertVariable('menus', $element['id'], $var, FALSE);
      if($duplicate)
        $this->AddOverlay($element['id'], $this->graph->NewID());
    } else {
      // add a placeholder to make sure the variable exists
      $ignore_id = $this->graph->NewID();
      $this->InsertVariable('menus', $ignore_id, "''", FALSE);
    }

    // set up menus after duplication
    $this->AddFunction('contextMenu');
  }

  /**
   * Sets click show/hide for an element
   * If using with fading, this must be used first
   */
  public function SetClickShow(&$element, $target, $hidden, $duplicate = FALSE)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->NewID();
    $id = $duplicate ? $this->graph->NewID() : $element['id'];
    if($duplicate)
      $this->AddOverlay($element['id'], $id);

    $this->AddFunction('clickShowEvent');
    $show = $hidden ? 0 : 1;
    $this->InsertVariable('clickElements', $element['id'], "'$target'", FALSE);
    $this->InsertVariable('clickMap', $target, $show, FALSE);
    $this->clickshow_enabled = true;
  }

  /**
   * Sets pop to front for $target when mouse over $element
   */
  public function SetPopFront(&$element, $target, $duplicate = FALSE)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->NewID();
    $id = $duplicate ? $this->graph->NewID() : $element['id'];
    if($duplicate)
      $this->AddOverlay($element['id'], $id);

    $this->AddFunction('popFront');
    $this->InsertVariable('popfronts', $element['id'],
      "{id:'{$target}'}", FALSE);
  }

  /**
   * Sets the fader for an element
   * If using with clickShow, that must be used first
   */
  public function SetFader(&$element, $in, $out, $target = NULL,
    $duplicate = FALSE)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->NewID();
    if(is_null($target))
      $target = $element['id'];
    $id = $duplicate ? $this->graph->NewID() : $element['id'];
    if($this->compat_events) {
      if($in) {
        $this->AddFunction('fadeIn');
        $this->AddEventHandler($element, 'onmouseover',
          'fadeIn(evt,"' . $target . '", ' . $in . ')');
      }
      if($out) {
        $this->AddFunction('fadeOut');
        $this->AddEventHandler($element, 'onmouseout',
          'fadeOut(evt,"' . $target . '", ' . $out . ')');
      }
    } else {

      $this->AddFunction('fadeEvent');
      if($in) {
        $this->AddFunction('fadeEventIn');
        $this->InsertVariable('fistep', $in, NULL, FALSE);
      }
      if($out) {
        $this->AddFunction('fadeEventOut');
        $this->InsertVariable('fostep', -$out, NULL, FALSE);
      }
      $this->InsertVariable('fades', $element['id'],
        "{id:'{$target}',dir:0}", FALSE);
      $this->InsertVariable('fstart', $in ? 0 : 1, NULL, FALSE);
    }
    if($duplicate)
      $this->AddOverlay($element['id'], $id);
    $this->fader_enabled = true;
  }

  /**
   * Makes an item draggable
   */
  public function SetDraggable(&$element)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->NewID();
    if($this->compat_events) {
      $this->AddFunction('dragOver');
      $this->AddFunction('dragStart');
      $this->AddFunction('dragEnd');
      $this->AddFunction('svgCursorCoords');
      $this->AddEventHandler($element, 'onmousemove',
        "dragOver(evt,'$element[id]')");
      $this->AddEventHandler($element, 'onmousedown',
        "dragStart(evt,'$element[id]')");
      $this->AddEventHandler($element, 'onmouseup',
        "dragEnd(evt,'$element[id]')");
    } else {
      $this->AddFunction('dragEvent');
      $this->InsertVariable('draggable', $element['id'], 0);
    }
  }

  /**
   * Makes something auto-hide
   */
  public function AutoHide(&$element)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->NewID();
    if($this->compat_events) {
      $this->AddFunction('setattr');
      $this->AddFunction('getE');
      $this->AddEventHandler($element, 'onmouseover',
        "setattr(getE('$element[id]'),'opacity',0)");
      $this->AddEventHandler($element, 'onmouseout',
        "setattr(getE('$element[id]'),'opacity',1)");
    } else {
      $this->AddFunction('autoHide');
      $this->InsertVariable('autohide', $element['id'], 0);
    }
  }

  /**
   * Add an overlaid copy of an element, with opacity of 0
   */
  public function AddOverlay($from, $to)
  {
    $this->AddFunction('duplicate');
    $this->InsertVariable('dups', $from, $to);
  }

  /**
   * Returns the variables (and comments) as Javascript code
   */
  public function GetVariables()
  {
    $variables = '';
    if(count($this->variables)) {
      $vlist = array();
      foreach($this->variables as $name => $value) {
        $var = $name;
        if(is_array($value)) {
          if(isset($value[0]) && isset($value[count($value)-1])) {
            $var .= '=[' . implode(',', $value) . ']';
          } else {
            $vs = array();
            foreach($value as $k => $v)
              if($k)
                $vs[] = "$k:$v";

            $var .= '={' . implode(',', $vs) . '}';
          }
        } elseif(!is_null($value)) {
          $var .= "=$value";
        }
        $vlist[] = $var;
      }
      $variables = "var " . implode(', ', $vlist) . ";";
    }
    // comments can be stuck with the variables
    if(count($this->comments)) {
      foreach($this->comments as $c) {
        if(!is_string($c))
          $c = print_r($c, TRUE);
        $variables .= "\n// " . str_replace("\n", "\n// ", $c);
      }
    }
    return $variables;
  }


  /**
   * Returns the functions as Javascript code
   */
  public function GetFunctions()
  {
    $functions = '';
    if(count($this->functions))
      $functions = implode('', $this->functions);
    return $functions;
  }

  /**
   * Returns the onload code to use for the SVG
   */
  public function GetOnload()
  {
    return $this->onload ? 'init()' : '';
  }

}

