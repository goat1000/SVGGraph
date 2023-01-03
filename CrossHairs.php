<?php
/**
 * Copyright (C) 2019-2023 Graham Breach
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

class CrossHairs {

  private $graph;
  private $show_h = false;
  private $show_v = false;

  private $left, $top, $width, $height;
  private $x_axis, $y_axis;
  private $assoc;
  private $flip_axes;
  private $encoding;

  public function __construct(&$graph, $left, $top, $w, $h, $x_axis, $y_axis,
    $assoc, $flip_axes, $encoding)
  {
    if($graph->getOption('crosshairs')) {
      $this->show_h = $graph->getOption('crosshairs_show_h');
      $this->show_v = $graph->getOption('crosshairs_show_v');
    }

    if(!$this->show_h && !$this->show_v)
      return;

    $this->left = $left;
    $this->top = $top;
    $this->width = $w;
    $this->height = $h;
    $this->x_axis = $x_axis;
    $this->y_axis = $y_axis;
    $this->assoc = $assoc;
    $this->flip_axes = $flip_axes;
    $this->encoding = $encoding;
    $this->graph =& $graph;
  }

  /**
   * Returns TRUE if the crosshairs are enabled
   */
  public function enabled()
  {
    return $this->show_h || $this->show_v;
  }

  /**
   * Returns a horizontal or vertical hair
   */
  private function getHair($orientation, $ch)
  {
    // line is always added, stays hidden if not enabled
    if($orientation == 'h')
      $hch = ['class' => 'chX', 'x2' => $ch['x1'] + $this->width];
    else
      $hch = ['class' => 'chY', 'y2' => $ch['y1'] + $this->height];

    $show = 'show_' . $orientation;
    if($this->$show) {
      $stroke = $this->graph->getOption('crosshairs_colour_' . $orientation,
        'crosshairs_colour');
      $hch['stroke'] = new Colour($this->graph, $stroke, false, false);
      $hch['stroke-width'] = $this->graph->getOption(
        'crosshairs_stroke_width_' . $orientation, 'crosshairs_stroke_width');
      $opacity = $this->graph->getOption(
        'crosshairs_opacity_' . $orientation, 'crosshairs_opacity');
      if($opacity > 0 && $opacity < 1)
        $hch['opacity'] = $opacity;
      $dash = $this->graph->getOption('crosshairs_dash_' . $orientation,
        'crosshairs_dash');
      if(!empty($dash))
        $hch['stroke-dasharray'] = $dash;
    }
    return $this->graph->element('line', array_merge($ch, $hch));
  }

  /**
   * Returns the crosshair code and also adds the JS and defs
   */
  public function getCrossHairs()
  {
    if(!($this->show_v || $this->show_h))
      return '';

    // make the crosshair lines
    $crosshairs = '';
    $ch = [
      'x1' => $this->left, 'y1' => $this->top,
      'x2' => $this->left, 'y2' => $this->top,
      'visibility' => 'hidden', // don't show them to start with!
    ];

    $crosshairs .= $this->getHair('h', $ch);
    $crosshairs .= $this->getHair('v', $ch);

    $text_options = [
      'back_colour', 'round', 'stroke_width', 'colour', 'font_size', 'font',
      'font_weight', 'padding', 'space',
    ];
    $t_opt = [];
    foreach($text_options as $opt)
      $t_opt[$opt] = $this->graph->getOption('crosshairs_text_' . $opt);

    // text group for grid details
    $text_group = ['id' => $this->graph->newId(), 'visibility' => 'hidden'];
    $text_rect = [
      'x' => '0', 'y' => '0', 'width' => '10', 'height' => '10',
      'fill' => new Colour($this->graph, $t_opt['back_colour']),
    ];
    if($t_opt['round'])
      $text_rect['rx'] = $text_rect['ry'] = $t_opt['round'];
    if($t_opt['stroke_width']) {
      $text_rect['stroke-width'] = $t_opt['stroke_width'];
      $text_rect['stroke'] = $t_opt['colour'];
    }
    $font_size = max(3, (int)$t_opt['font_size']);
    $text_element = [
      'x' => 0, 'y' => $font_size,
      'font-family' => $t_opt['font'],
      'font-size' => $font_size,
      'fill' => new Colour($this->graph, $t_opt['colour']),
    ];
    $weight = $t_opt['font_weight'];
    if($weight && $weight != 'normal')
      $text_element['font-weight'] = $weight;

    $svg_text = new Text($this->graph);
    $text = $this->graph->element('g', $text_group, null,
      $this->graph->element('rect', $text_rect) .
      $svg_text->text('', $font_size, $text_element));
    $this->graph->addBackMatter($text);

    // add in the details of the grid scales
    $zero_x = $this->x_axis->zero();
    $scale_x = $this->x_axis->unit();
    $zero_y = $this->y_axis->zero();
    $scale_y = $this->y_axis->unit();
    $prec_x = $this->graph->getOption('crosshairs_text_precision_h',
      max(0, ceil(log10($scale_x))));
    $prec_y = $this->graph->getOption('crosshairs_text_precision_v',
      max(0, ceil(log10($scale_y))));

    $scale_x = new Number($scale_x);
    $scale_x->precision = 7;
    $scale_y = new Number($scale_y);
    $scale_y->precision = 7;

    $gridx_attrs = [
      'function' => 'strValueX',
      'zero' => $zero_x,
      'scale' => $scale_x,
      'precision' => $prec_x,
    ];
    $gridy_attrs = [
      'function' => 'strValueY',
      'zero' => $zero_y,
      'scale' => $scale_y,
      'precision' => $prec_y,
    ];
    $chtextitem_attrs = [
      'type' => 'xy',
      'groupid' => $text_group['id'],
    ];
    $u = $this->x_axis->afterUnits();
    if(!empty($u))
      $chtextitem_attrs['unitsx'] = $u;
    $u = $this->y_axis->afterUnits();
    if(!empty($u))
      $chtextitem_attrs['unitsy'] = $u;
    $u = $this->x_axis->beforeUnits();
    if(!empty($u))
      $chtextitem_attrs['unitsbx'] = $u;
    $u = $this->y_axis->beforeUnits();
    if(!empty($u))
      $chtextitem_attrs['unitsby'] = $u;

    $log_x = $this->graph->getOption(['log_axis_x', 0]);
    $log_y = $this->graph->getOption(['log_axis_y', 0]);
    if($log_x || $log_y) {
      $base_y = $this->graph->getOption('log_axis_y_base');
      $base_x = $this->graph->getOption('log_axis_x_base');
      $log_h = $this->flip_axes ? $log_y : $log_x;
      $log_v = $this->flip_axes ? $log_x : $log_y;

      if($log_h) {
        $gridx_attrs['base'] = $this->flip_axes ? $base_y : $base_x;
        $gridx_attrs['zero'] = $this->x_axis->value(0);
        $gridx_attrs['scale'] = $this->x_axis->value($this->width);
        $this->graph->getJavascript()->addFunction('logStrValueX');
        $gridx_attrs['function'] = 'logStrValueX';
      }
      if($log_v) {
        $gridy_attrs['base'] = $this->flip_axes ? $base_x : $base_y;
        $gridy_attrs['zero'] = $this->y_axis->value(0);
        $gridy_attrs['scale'] = $this->y_axis->value($this->height);
        $this->graph->getJavascript()->addFunction('logStrValueY');
        $gridy_attrs['function'] = 'logStrValueY';
      }
    }

    if($this->graph->getOption('datetime_keys') &&
      (method_exists($this->x_axis, 'GetFormat') ||
      method_exists($this->y_axis, 'GetFormat'))) {
      $dtf = new DateTimeFormatter;
      if($this->flip_axes) {
        $this->graph->getJavascript()->addFunction('dateStrValueY');
        $zy = (int)$this->y_axis->value(0);
        $ey = (int)$this->y_axis->value($this->width);
        $dt = new \DateTime('@' . $zy);
        $gridy_attrs['scale'] = ($ey - $zy) / $this->height;
        $gridy_attrs['zero'] = $dtf->format($dt, 'c', true);
        $gridy_attrs['function'] = 'dateStrValueY';
        $gridy_attrs['format'] = $this->y_axis->getFormat();
      } else {
        $this->graph->getJavascript()->addFunction('dateStrValueX');
        $zx = (int)$this->x_axis->value(0);
        $ex = (int)$this->x_axis->value($this->width);
        $dt = new \DateTime('@' . $zx);
        $gridx_attrs['scale'] = ($ex - $zx) / $this->width;
        $gridx_attrs['zero'] = $dtf->format($dt, 'c', true);
        $gridx_attrs['function'] = 'dateStrValueX';
        $gridx_attrs['format'] = $this->x_axis->getFormat();
      }
      $long_days = $dtf->getLongDays();
      $short_days = $dtf->getShortDays();
      $long_months = $dtf->getLongMonths();
      $short_months = $dtf->getShortMonths();
      foreach($long_days as $day)
        $this->graph->getJavascript()->insertVariable('daysLong', null, $day);
      foreach($short_days as $day)
        $this->graph->getJavascript()->insertVariable('daysShort', null, $day);
      foreach($long_months as $month)
        $this->graph->getJavascript()->insertVariable('monthsLong', null, $month);
      foreach($short_months as $month)
        $this->graph->getJavascript()->insertVariable('monthsShort', null, $month);
    }

    // build associative data keys XML
    $keys_xml = '';
    if($this->assoc) {

      $k_max = $this->graph->getMaxKey();
      for($i = 0; $i <= $k_max; ++$i) {
        $k = $this->graph->getKey($i);
        $keys_xml .= $this->graph->element('svggraph:key', ['value' => $k]);
      }
      $keys_xml = $this->graph->element('svggraph:keys', null, null, $keys_xml);

      // choose a rounding function
      $round_function = 'kround';
      if($this->graph->getOption('label_centre'))
        $round_function = 'kroundDown';
      $this->graph->getJavascript()->addFunction($round_function);

      // set the string function
      if($this->flip_axes) {
        $this->graph->getJavascript()->addFunction('keyStrValueY');
        $gridy_attrs['function'] = 'keyStrValueY';
        $gridy_attrs['round'] = $round_function;
      } else {
        $this->graph->getJavascript()->addFunction('keyStrValueX');
        $gridx_attrs['function'] = 'keyStrValueX';
        $gridx_attrs['round'] = $round_function;
      }
    }

    $gridx = $this->graph->element('svggraph:gridx', $gridx_attrs);
    $gridy = $this->graph->element('svggraph:gridy', $gridy_attrs);
    $chtext = $this->graph->element('svggraph:chtext', null, null,
      $this->graph->element('svggraph:chtextitem', $chtextitem_attrs));

    $xml = $gridx . $gridy . $chtext . $keys_xml;
    $defs = $this->graph->element('svggraph:data',
      ['xmlns:svggraph' => 'http://www.goat1000.com/svggraph'], null, $xml);
    $this->graph->defs->add($defs);

    // add the main function at the end - it can fill in any defaults
    $this->graph->getJavascript()->addFunction('crosshairs');
    return $crosshairs;
  }
}

