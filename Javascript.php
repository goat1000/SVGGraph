<?php
/**
 * Copyright (C) 2012-2023 Graham Breach
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
  protected $init_functions = [];
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
    if($realname === null)
      $realname = $name;

    if(isset($this->functions[$realname]))
      return true;

    // functions that fit on one line
    $simple_functions = [
      'setattr' =>
        "function setattr(i,a,v){i.setAttributeNS(null,a,v);return v}\n",
      'getE' =>
        "function getE(i){return document.getElementById(i)}\n",
      'newtext' =>
        "function newtext(c){return document.createTextNode(c)}\n",
      'textAttr' =>
        "function textAttr(e,a){var s=e.getAttributeNS(null,a);return s?s:'';}\n",
      // round to nearest whole number
      'kround' => "function kround(v){return Math.round(v)|0;}\n",
      // floor function
      'kroundDown' => "function kroundDown(v){return v|0;}\n",
    ];
    if(isset($simple_functions[$name]))
      return $this->insertFunction($name, $simple_functions[$name]);

    // functions that only use a template and other functions
    $template_functions = [
      'dateFormat' => [],
      'dateStrValueX' => ['dateFormat'],
      'dateStrValueY' => ['dateFormat'],
      'fading' => [],
      'finditem' => [],
      'fitRect' => ['setattr'],
      'getData' => [],
      'keyStrValueX' => [],
      'keyStrValueY' => [],
      'logStrValueX' => [],
      'logStrValueY' => [],
      'newel' => ['setattr'],
      'strValueX' => [],
      'strValueY' => [],
      'svgCursorCoords' => ['svgNode'],
    ];
    if(isset($template_functions[$name])) {
      foreach($template_functions[$name] as $dependency)
        $this->addFunction($dependency);
      return $this->insertTemplate($name);
    }

    switch($name)
    {
    case 'showhide' :
      $this->addFunction('setattr');
      $fn = "function showhide(e,h){setattr(e,'visibility',h?'visible':'hidden');}\n";
      break;

    case 'tooltip' :
      $this->addFuncs('getE', 'setattr', 'newel', 'showhide', 'svgNode',
        'svgCursorCoords');
      $this->insertVariable('tooltipOn', '');
      $opts = ['stroke_width', 'shadow_opacity', 'round', 'padding',
        'back_colour', 'offset', 'align'];
      $vars = [];
      foreach($opts as $opt) {
        $vars[$opt] = $this->graph->getOption('tooltip_' . $opt);
      }

      $round_part = '';
      $shadow_part = '';
      $vars['edge_space'] = $vars['stroke_width'];
      $vars['stroke'] = $this->graph->getOption('tooltip_stroke_colour',
        'tooltip_colour', ['@','#000']);
      if($vars['round'] > 0) {
        $round = new Number($vars['round'], 'px');
        $round_part = ',rx:"' . $round . '",ry:"' . $round . '"';
      }

      if(is_numeric($vars['shadow_opacity'])) {
        $ttoffs = 2 - $vars['stroke_width'] / 2;
        $vars['edge_space'] += $ttoffs;
        $ttoffs = new Number($ttoffs, 'px');
        $shadow_part = 'shadow = newel("rect",{id:"ttshdw",fill:"#000",' .
          'width:"10px",height:"10px",opacity:' .
          new Number($vars['shadow_opacity']) .
          ',x:"' . $ttoffs . '",y:"' . $ttoffs . '"';
        if($round_part !== '')
          $shadow_part .= $round_part;
        $shadow_part .= '});';
        $shadow_part .= 'tt.appendChild(shadow);';
      }

      $vars['transform_part'] = "setattr(inner, 'transform', 'translate(";
      switch($vars['align']) {
      case 'left' :
        $vars['transform_part'] .= new Number($vars['padding']) . ",0)');";
        break;
      case 'right' :
        $vars['transform_part'] .= "' + (bw - " .  new Number($vars['padding']) . ") + ',0)');";
        break;
      default:
        $vars['transform_part'] .= "' + (bw / 2) + ',0)');";
      }

      $vars['round_part'] = $round_part;
      $vars['shadow_part'] = $shadow_part;
      $vars['dpad'] = 2 * $vars['padding'];
      $vars['back_colour'] = new Colour($this->graph, $vars['back_colour']);
      $vars['stroke'] = new Colour($this->graph, $vars['stroke']);
      return $this->insertTemplate('tooltip', $vars);

    case 'texttt' :
      $this->addFuncs('getE', 'setattr', 'newel', 'newtext');
      $opts = ['padding', 'colour', 'font', 'font_weight', 'align'];
      $vars = [];
      foreach($opts as $opt)
        $vars[$opt] = $this->graph->getOption('tooltip_' . $opt);
      $vars['font_size'] = Number::units($this->graph->getOption('tooltip_font_size'));
      $vars['line_spacing'] = Number::units($this->graph->getOption('tooltip_line_spacing'));
      $vars['colour'] = new Colour($this->graph, $vars['colour'], false, false);
      $vars['ttoffset'] = $vars['font_size'] + $vars['padding'];
      if($vars['line_spacing'] === null || $vars['line_spacing'] < 1)
        $vars['tty'] = $vars['ttoffset'];
      else
        $vars['tty'] = $vars['line_spacing'];

      $anchors = ['left' => 'start', 'right' => 'end'];
      $vars['anchor'] = isset($anchors[$vars['align']]) ?
        $anchors[$vars['align']] : 'middle';
      return $this->insertTemplate('texttt', $vars);

    case 'ttEvent' :
      $this->addFuncs('finditem');
      $this->addInitFunction('ttEvent');
      return $this->insertTemplate('ttEvent');

    case 'popFront' :
      $this->addFuncs('getE', 'finditem');
      $this->addInitFunction('popFront');
      return $this->insertTemplate('popFront');

    case 'clickShowEvent' :
      if($this->fader_enabled)
        return $this->fadeAndClick();

      $this->addFuncs('getE', 'finditem', 'setattr');
      $this->addInitFunction('clickShowEvent');
      return $this->insertTemplate('clickShowEvent');

    case 'fade' :
      if($this->clickshow_enabled)
        return $this->fadeAndClick();

      $this->addFuncs('getE', 'setattr', 'textAttr');
      $this->addInitFunction('fade');
      return $this->insertTemplate('fade');

    case 'fadeEventIn' :
      $this->addFuncs('finditem');
      $this->addInitFunction('fadeEventIn');
      return $this->insertTemplate('fadeEventIn');

    case 'fadeEventOut' :
      $this->addFuncs('finditem');
      $this->addInitFunction('fadeEventOut');
      return $this->insertTemplate('fadeEventOut');

    case 'duplicate' :
      $this->addFuncs('getE', 'newel', 'setattr');
      $this->addInitFunction('initDups');
      return $this->insertTemplate('duplicate', ['namespace' => $this->namespace]);

    case 'svgNode' :
      return $this->insertTemplate('svgNode', ['namespace' => $this->namespace]);

    case 'autoHide' :
      $this->addFuncs('getE', 'setattr', 'finditem');
      $this->addInitFunction('autoHide');
      return $this->insertTemplate('autoHide');

    case 'chEvt' :
      $this->addInitFunction('chEvt');
      return $this->insertTemplate('chEvt');

    case 'showCoords' :
      $this->addFuncs('getE', 'newel', 'newtext', 'getData', 'showhide',
        'fitRect', 'textAttr', 'strValueX', 'strValueY');

      // format text for assoc X, assoc Y or x,y
      $text_format_x = 'fnx(de,x,bb.width,gx,' .
        'textAttr(ti,"unitsbx"),textAttr(ti,"unitsx"))';
      $text_format_y = 'fny(de,bb.height-y,bb.height,gy,' .
        'textAttr(ti,"unitsby"),textAttr(ti,"unitsy"))';

      if(!$this->graph->getOption('crosshairs_show_h'))
        $text_format = $text_format_x;
      elseif(!$this->graph->getOption('crosshairs_show_v'))
        $text_format = $text_format_y;
      else
        $text_format = $text_format_x . ' + ", " + ' . $text_format_y;

      $pad = max(0, (int)$this->graph->getOption('crosshairs_text_padding'));
      $space = max(0, (int)$this->graph->getOption('crosshairs_text_space'));
      $vars = [
        'text_format' => $text_format,
        'pad' => $pad,
        // calculate these here to save doing it in JS
        'pad_space' => $pad + $space,
        'space2' => $space * 2,
      ];
      return $this->insertTemplate('showCoords', $vars);

    case 'crosshairs' :
      $this->addFuncs('chEvt', 'setattr', 'svgNode', 'svgCursorCoords',
        'showhide');

      $vars = [ 'show_x' => '', 'show_y' => '', 'show_text' => ''];
      if($this->graph->getOption('crosshairs_show_text')) {
        $this->addFunction('showCoords');
        $vars['show_text'] = 'showCoords(de, x - bb.x, y - bb.y, bb, on);';
      }
      if($this->graph->getOption('crosshairs_show_h'))
        $vars['show_x'] = 'showhide(xc,on);';
      if($this->graph->getOption('crosshairs_show_v'))
        $vars['show_y'] = 'showhide(yc,on);';
      return $this->insertTemplate('crosshairs', $vars);

    case 'dragEvent' :
      $this->addFuncs('newel', 'getE', 'setattr', 'finditem', 'svgCursorCoords');
      $this->addInitFunction('dragEvent');
      return $this->insertTemplate('dragEvent');

    case 'magEvt' :
      $this->addInitFunction('magEvt');
      $vars = ['namespace' => $this->namespace];
      return $this->insertTemplate('magEvt', $vars);

    default :
      // Trying to add a function that doesn't exist?
      throw new \Exception('Unknown function "' . $name . '"');
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
   * Inserts a function from a template
   */
  public function insertTemplate($name, $vars = null, $realname = null)
  {
    if($realname === null)
      $realname = $name;
    $file_path = __DIR__ . '/templates/' . $name . '.txt';
    if(!file_exists($file_path))
      throw new \Exception('Template [' . $name . '.txt] not found.');
    $content = file_get_contents($file_path);

    if($vars !== null) {
      // insert variables into template
      $content = preg_replace_callback('/{\$([a-z]+):([a-z0-9_]+)}/',
        function($m) use($vars) {
          list(, $type, $var) = $m;
          if(!isset($vars[$var]))
            throw new \Exception('Variable [' . $var . '] not defined.');

          $value = $vars[$var];
          if('number' === $type) {
            if(is_numeric($value))
              return new Number($value);

            if(is_object($value) && get_class($value) === 'Goat1000\\SVGGraph\\Number')
              return $value;

            throw new \Exception('Variable [' . $var . '] not numeric, value "' .
              $value . '".');
          }
          return $value; }, $content);
    }

    $this->insertFunction($realname, $content);
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
    $q = $quote ? '"' : '';
    if($more === null)
      $this->variables[$var] = $q . $this->reEscape($value) . $q;
    elseif($value === null)
      $this->variables[$var][] = $q . $this->reEscape($more) . $q;
    else
      $this->variables[$var][$value] = $q . $this->reEscape($more) . $q;
  }

  /**
   * Insert a numeric variable
   */
  public function insertNumberVar($var, $value)
  {
    $this->variables[$var] = new Number($value);
  }

  /**
   * Adds an init function to the list
   */
  public function addInitFunction($name)
  {
    $this->init_functions[$name] = 1;
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
    $this->addFuncs('getE', 'finditem', 'fading', 'textAttr', 'setattr');
    $this->addInitFunction('clickShowEvent');
    $this->addInitFunction('fade');
    $this->insertTemplate('clickShowEvent_fade', null, 'clickShowEvent');
    $this->insertTemplate('fade_clickShow', null, 'fade');
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
    $this->insertVariable('clickElements', $element['id'], $target);
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
    $this->insertVariable('popfronts', $element['id'], $target);
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
    if($target === null)
      $target = $element['id'];
    $id = $duplicate ? $this->graph->newID() : $element['id'];

    $this->addFunction('fade');
    if($in) {
      $this->addFunction('fadeEventIn');
      $this->insertNumberVar('fistep', $in);
    }
    if($out) {
      $this->addFunction('fadeEventOut');
      $this->insertNumberVar('fostep', -$out);
    }
    $this->insertVariable('fades', $element['id'],
      '{id:"' . $target . '",dir:0}', false);
    $this->insertNumberVar('fstart', $in ? 0 : 1);

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
  public function autoHide(&$element, $hidden_opacity = 0, $full_opacity = 1)
  {
    if(!isset($element['id']))
      $element['id'] = $this->graph->newID();
    $this->addFunction('autoHide');
    $this->insertVariable('autohide', $element['id'], 0);
    $this->insertVariable('ah_opacity', $element['id'], '[' .
      new Number($hidden_opacity) . ',' .
      new Number($full_opacity) . ']', false);
  }

  /**
   * Adds magnifier
   */
  public function magnifier()
  {
    $max_mag = 10.0;
    $min_mag = 1.1;
    $max_pan = 10.0;
    $min_pan = 1.1;
    $mag = (float)$this->graph->getOption('magnify');
    $pan = (float)$this->graph->getOption('magnify_pan');
    if($mag <= $min_mag)
      $mag = $min_mag;
    elseif($mag > $max_mag)
      $mag = $max_mag;
    if($pan <= $min_pan)
      $pan = $min_pan;
    elseif($pan > $max_pan)
      $pan = $max_pan;

    $vars = [
      'magnification' => $mag,
      'sensitivity' => $pan,
      'namespace' => $this->namespace,
    ];
    $this->addFuncs('magEvt','svgNode','svgCursorCoords','setattr');
    $this->insertTemplate('magnifier', $vars);
  }

  /**
   * Add an overlaid copy of an element, with opacity of 0
   */
  public function addOverlay($from, $to)
  {
    $this->addFunction('duplicate');

    // order matters, so clear previous value
    if(isset($this->variables['dups'][$from]))
      unset($this->variables['dups'][$from]);
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
                $vs[] = $k . ':' . $v;

            $var .= '={' . implode(',', $vs) . '}';
          }
        } elseif($value !== null) {
          $var .= '=' . $value;
        }
        $vlist[] = $var;
      }
      $variables = 'var ' . implode(', ', $vlist) . ';';
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
    if(count($this->functions)) {
      if(count($this->init_functions)) {
        $vars = [
          'init_funcs' => implode('();', array_keys($this->init_functions)) . '();'
        ];
        $this->insertTemplate('init', $vars);
      }
      $functions = implode('', $this->functions);
    }

    return $functions;
  }

  /**
   * Returns the onload code to use for the SVG
   */
  public function getOnload()
  {
    if(count($this->init_functions))
      return 'init()';
    return '';
  }

  /**
   * Returns all the code to be inserted into <script>
   */
  public function getCode($cdata, $minifier)
  {
    $variables = $this->getVariables();
    $functions = $this->getFunctions();
    $onload = $this->getOnload();

    if($variables == '' && $functions == '')
      return '';

    if($onload != '')
      $functions .= "\n" . 'setTimeout(function(){' . $onload . '},10);';

    $script = $variables  . "\n" . $functions . "\n";
    if(is_callable($minifier))
      $script = call_user_func($minifier, $script);
    elseif($minifier !== null)
      $script = $this->minify($script);

    // make closure
    $script = '(function(){' . $script . "\n})();";

    if($cdata)
      $script = "// <![CDATA[\n" . $script . "\n// ]]>";

    return $script;
  }

  /**
   * Simple minifier
   */
  public static function minify($code)
  {
    $start = strlen($code);
    $code = preg_replace(
      ['/^\s+/m', '/\s*([{}=+<>,;?:)\/*-]|&&|\|\|)\s*/', ],
      ['', '$1', ],
      $code);

    return $code;
  }
}

