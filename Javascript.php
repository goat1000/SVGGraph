<?php
/**
 * Copyright (C) 2012-2019 Graham Breach
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

class Javascript {

  private $graph;
  protected $functions = [];
  protected $variables = [];
  protected $comments = [];
  protected $onload = false;
  protected $fader_enabled = false;
  protected $clickshow_enabled = false;

  private $namespace = '';

  public function __construct(&$graph)
  {
    $this->graph =& $graph;

    if($graph->getOption('namespace'))
      $this->namespace = 'svg:';
  }

  /**
   * Adds any number of functions by name
   */
  public function addFuncs()
  {
    $fns = func_get_args();
    foreach($fns as $fn) {
      if(!isset($this->functions[$fn]))
        $this->addFunction($fn);
    }
  }

  /**
   * Adds a javascript function
   */
  public function addFunction($name, $realname = null)
  {
    if(is_null($realname))
      $realname = $name;

    if(isset($this->functions[$realname]))
      return true;

    $simple_functions = [
      'setattr' => "function setattr(i,a,v){i.setAttributeNS(null,a,v);return v}\n",
      'getE' => "function getE(i){return document.getElementById(i)}\n",
      'newtext' => "function newtext(c){return document.createTextNode(c)}\n",
    ];

    if(isset($simple_functions[$name])) {
      $this->insertFunction($name, $simple_functions[$name]);
      return;
    }

    $namespace = $this->namespace;

    switch($name)
    {
    case 'newel' :
      $this->addFunction('setattr');
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
      $this->addFunction('setattr');
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
    case 'tooltip' :
      $this->addFuncs('getE', 'setattr', 'newel', 'showhide', 'svgNode',
        'svgCursorCoords');
      $this->insertVariable('tooltipOn', '');
      $opts = ['stroke_width', 'shadow_opacity', 'round', 'padding', 'colour',
        'back_colour', 'offset'];
      foreach($opts as $opt)
        $$opt = $this->graph->getOption('tooltip_' . $opt);

      $round_part = $round > 0 ? "rx:{$round}px,ry:{$round}px," : '';
      $shadow_part = '';
      $edge_space = $stroke_width;

      if(is_numeric($shadow_opacity)) {
        $ttoffs = (2 - $stroke_width/2);
        $edge_space += $ttoffs;
        $shadow_part = <<<JAVASCRIPT
    shadow = newel('rect',{
      fill: '#000',
      opacity: {$shadow_opacity},
      x:'{$ttoffs}px',y:'{$ttoffs}px',{$round_part}
      width:'10px',height:'10px',
      id: 'ttshdw'
    });
    tt.appendChild(shadow);
JAVASCRIPT;
      }
      $dpad = 2 * $padding;
      $back_colour = $this->graph->parseColour($back_colour);
      $fn = <<<JAVASCRIPT
function tooltip(e,callback,on,param) {
  var tt = getE('tooltip'), rect = getE('ttrect'), shadow = getE('ttshdw'),
    offset = {$offset}, pos = svgCursorCoords(e),
    x = pos[0] + offset, y = pos[1] + offset, inner, brect, bw, bh,
    sw, sh, de = svgNode(e);
  if(on && !tt) {
    tt = newel('g',{id:'tooltip',visibility:'visible'});
    rect = newel('rect',{
      stroke: '{$colour}',
      'stroke-width': '{$stroke_width}px',
      fill: '{$back_colour}',{$round_part}
      width:'10px',height:'10px',
      id: 'ttrect'
    });
{$shadow_part}
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
      setattr(shadow, 'width', (bw + {$stroke_width}) + 'px');
      setattr(shadow, 'height', (bh + {$stroke_width}) + 'px');
    }
    if(bw + x > de.width.baseVal.value - {$edge_space}) {
      x -= bw + offset * 2;
      x = Math.max(x, 0);
    }
    if(bh + y > de.height.baseVal.value - {$edge_space}) {
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
      $this->addFuncs('getE', 'setattr', 'newel', 'newtext');
      $opts = ['padding', 'colour', 'font', 'font_size', 'font_weight'];
      foreach($opts as $opt)
        $$opt = $this->graph->getOption('tooltip_' . $opt);

      $tty = $font_size + $padding;
      $ttypx = "{$tty}px";
      $fn = <<<JAVASCRIPT
function texttt(e,tt,on,t){
  var ttt = getE('tooltiptext'), lines, i, ts, xpos;
  if(on) {
    lines = t.split('\\\\n');
    xpos = '{$padding}px';
    if(!ttt) {
      ttt = newel('g', {
        id: 'tooltiptext',
        fill: '{$colour}',
        'font-size': '{$font_size}px',
        'font-family': '{$font}',
        'font-weight': '{$font_weight}',
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
      $this->addFuncs('finditem', 'init');
      $this->insertVariable('initfns', null, 'ttEvt');
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
      $this->addFuncs('getE', 'init', 'finditem');
      $this->insertVariable('initfns', null, 'popFrontInit');
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
        return $this->fadeAndClick();

      $this->addFuncs('getE', 'init', 'finditem', 'setattr');
      $this->insertVariable('initfns', null, 'clickShowInit');
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
        return $this->fadeAndClick();

      $this->addFuncs('getE', 'init', 'setattr', 'textAttr');
      $this->insertVariable('initfns', null, 'fade');
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
      $this->addFuncs('init', 'finditem');
      $this->insertVariable('initfns', null, 'fiEvt');
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
      $this->addFuncs('init', 'finditem');
      $this->insertVariable('initfns', null, 'foEvt');
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
      $this->addFuncs('getE', 'newel', 'init', 'setattr');
      $this->insertVariable('initfns', null, 'initDups');
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
      $this->addFunction('svgNode');
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
      $this->addFuncs('init', 'getE', 'setattr', 'finditem');
      $this->insertVariable('initfns', null, 'autoHide');
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
      $this->addFunction('init');
      $this->insertVariable('initfns', null, 'chEvt');
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
      $this->addFunction('setattr');
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
      $this->addFunction('dateFormat');
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
      $this->addFunction('dateFormat');
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
      $this->addFuncs('getE', 'newel', 'newtext', 'getData', 'showhide',
        'fitRect', 'textAttr', 'strValueX', 'strValueY');

      $opts = ['show_h', 'show_v', 'text_font_size', 'text_padding',
        'text_space'];
      foreach($opts as $opt)
        $$opt = $this->graph->getOption('crosshairs_' . $opt);

      // format text for assoc X, assoc Y or x,y
      $yb = "textAttr(ti,'unitsby')";
      $ya = "textAttr(ti,'unitsy')";
      $xb = "textAttr(ti,'unitsbx')";
      $xa = "textAttr(ti,'unitsx')";
      $text_format_x = "window[fnx](de,x,bb.width,gx,{$xb},{$xa})";
      $text_format_y = "window[fny](de,bb.height-y,bb.height,gy,{$yb},{$ya})";

      if(!$show_h)
        $text_format = $text_format_x;
      elseif(!$show_v)
        $text_format = $text_format_y;
      else
        $text_format = "{$text_format_x} + ', ' + {$text_format_y}";

      $font_size = max(3, (int)$text_font_size);
      $pad = max(0, (int)$text_padding);
      $space = max(0, (int)$text_space);
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
      $this->addFuncs('chEvt', 'setattr', 'svgNode', 'svgCursorCoords',
        'showhide');
      $opts = ['show_h', 'show_v', 'show_text'];
      foreach($opts as $opt)
        $$opt = $this->graph->getOption('crosshairs_' . $opt);
      if($show_text) {
        $this->addFunction('showCoords');
        $show_text = "showCoords(de, x - bb.x, y - bb.y, bb, on);";
      } else {
        $show_text = '';
      }
      $show_x = $show_h ? 'showhide(xc, on);' : '';
      $show_y = $show_v ? 'showhide(yc, on);' : '';
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
    case 'dragEvent' :
      $this->addFuncs('init', 'newel', 'getE', 'setattr', 'finditem',
        'svgCursorCoords');
      $this->insertVariable('initfns', null, 'initDrag');
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
      $this->onload = true;
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
      throw new \Exception("Unknown function '$name'");
    }

    $this->insertFunction($realname, $fn);
  }

  /**
   * Inserts a Javascript function into the list
   */
  public function insertFunction($name, $fn)
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

  public static function reEscape($string)
  {
    // convert XML char entities to JS unicode
    $string = preg_replace_callback('/&#x([a-f0-9]+);/',
      'Goat1000\\SVGGraph\\Javascript::hex2js', $string);
    $string = preg_replace_callback('/&#([0-9]+);/',
      'Goat1000\\SVGGraph\\Javascript::dec2js', $string);
    return $string;
  }

  /**
   * Adds a Javascript variable
   * - use $value:$more for assoc
   * - use NULL:$more for array
   */
  public function insertVariable($var, $value, $more = null, $quote = true)
  {
    $q = $quote ? "'" : '';
    if(is_null($more))
      $this->variables[$var] = $q . $this->reEscape($value) . $q;
    elseif(is_null($value))
      $this->variables[$var][] = $q . $this->reEscape($more) . $q;
    else
      $this->variables[$var][$value] = $q . $this->reEscape($more) . $q;
  }

  /**
   * Insert a comment into the Javascript section - handy for debugging!
   */
  public function insertComment($details)
  {
    $this->comments[] = $details;
  }

  /**
   * Fade and click at the same time requires different functions
   */
  private function fadeAndClick()
  {
    $this->addFuncs('getE', 'init', 'finditem', 'fading', 'textAttr', 'setattr');
    $this->insertVariable('initfns', null, 'clickShowInit');
    $this->insertVariable('initfns', null, 'fade');
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
    $this->insertFunction('clickShowEvent', $fn);

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
    $this->insertFunction('fadeEvent', $fn);
  }

  /**
   * Sets the tooltip for an element
   */
  public function setTooltip(&$element, $text, $duplicate = false)
  {
    $this->addFuncs('tooltip', 'texttt', 'ttEvent');
    if(!isset($element['id']))
      $element['id'] = $this->graph->newID();
    $this->insertVariable('tips', $element['id'], $text);

    if($duplicate) {
      if(!isset($element['id']))
        $element['id'] = $this->graph->newID();
      $this->addOverlay($element['id'], $this->graph->newID());
    }
  }

  /**
   * Sets click show/hide for an element
   * If using with fading, this must be used first
   */
  public function setClickShow(&$element, $target, $hidden, $duplicate = false)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->newID();
    $id = $duplicate ? $this->graph->newID() : $element['id'];
    if($duplicate)
      $this->addOverlay($element['id'], $id);

    $this->addFunction('clickShowEvent');
    $show = $hidden ? 0 : 1;
    $this->insertVariable('clickElements', $element['id'], "'$target'", false);
    $this->insertVariable('clickMap', $target, $show, false);
    $this->clickshow_enabled = true;
  }

  /**
   * Sets pop to front for $target when mouse over $element
   */
  public function setPopFront(&$element, $target, $duplicate = false)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->newID();
    $id = $duplicate ? $this->graph->newID() : $element['id'];
    if($duplicate)
      $this->addOverlay($element['id'], $id);

    $this->addFunction('popFront');
    $this->insertVariable('popfronts', $element['id'],
      "{id:'{$target}'}", false);
  }

  /**
   * Sets the fader for an element
   * If using with clickShow, that must be used first
   */
  public function setFader(&$element, $in, $out, $target = null,
    $duplicate = false)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->newID();
    if(is_null($target))
      $target = $element['id'];
    $id = $duplicate ? $this->graph->newID() : $element['id'];

    $this->addFunction('fadeEvent');
    if($in) {
      $this->addFunction('fadeEventIn');
      $this->insertVariable('fistep', $in, null, false);
    }
    if($out) {
      $this->addFunction('fadeEventOut');
      $this->insertVariable('fostep', -$out, null, false);
    }
    $this->insertVariable('fades', $element['id'],
      "{id:'{$target}',dir:0}", false);
    $this->insertVariable('fstart', $in ? 0 : 1, null, false);

    if($duplicate)
      $this->addOverlay($element['id'], $id);
    $this->fader_enabled = true;
  }

  /**
   * Makes an item draggable
   */
  public function setDraggable(&$element)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->newID();
    $this->addFunction('dragEvent');
    $this->insertVariable('draggable', $element['id'], 0);
  }

  /**
   * Makes something auto-hide
   */
  public function autoHide(&$element)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->newID();
    $this->addFunction('autoHide');
    $this->insertVariable('autohide', $element['id'], 0);
  }

  /**
   * Add an overlaid copy of an element, with opacity of 0
   */
  public function addOverlay($from, $to)
  {
    $this->addFunction('duplicate');
    $this->insertVariable('dups', $from, $to);
  }

  /**
   * Returns the variables (and comments) as Javascript code
   */
  public function getVariables()
  {
    $variables = '';
    if(count($this->variables)) {
      $vlist = [];
      foreach($this->variables as $name => $value) {
        $var = $name;
        if(is_array($value)) {
          if(isset($value[0]) && isset($value[count($value)-1])) {
            $var .= '=[' . implode(',', $value) . ']';
          } else {
            $vs = [];
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
          $c = print_r($c, true);
        $variables .= "\n// " . str_replace("\n", "\n// ", $c);
      }
    }
    return $variables;
  }

  /**
   * Returns the functions as Javascript code
   */
  public function getFunctions()
  {
    $functions = '';
    if(count($this->functions))
      $functions = implode('', $this->functions);
    return $functions;
  }

  /**
   * Returns the onload code to use for the SVG
   */
  public function getOnload()
  {
    return $this->onload ? 'init()' : '';
  }
}

