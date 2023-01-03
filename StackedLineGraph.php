<?php
/**
 * Copyright (C) 2012-2022 Graham Breach
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
 * StackedLineGraph - multiple joined lines with values added together
 */
class StackedLineGraph extends MultiLineGraph {

  public function __construct($w, $h, $settings, $fixed_settings = [])
  {
    $fixed = [ 'single_axis' => true ];
    $fixed_settings = array_merge($fixed, $fixed_settings);
    parent::__construct($w, $h, $settings, $fixed_settings);
  }

  protected function draw()
  {
    if($this->getOption('log_axis_y'))
      throw new \Exception('log_axis_y not supported by StackedLineGraph');

    $body = $this->grid() . $this->underShapes();
    $plots = [];
    $stack = [];

    $datasets = $this->multi_graph->getEnabledDatasets();
    foreach($datasets as $i) {
      $bnum = 0;
      $cmd = 'M';
      $path = new PathData;
      $fillpath = new PathData;
      $attr = ['fill' => 'none'];
      $fill = $this->getOption(['fill_under', $i]);
      $dash = $this->getOption(['line_dash', $i]);
      $stroke_width = $this->getOption(['line_stroke_width', $i]);
      if(!empty($dash))
        $attr['stroke-dasharray'] = $dash;
      $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;

      $bottom = [];
      $point_count = 0;
      foreach($this->multi_graph[$i] as $item) {
        $x = $this->gridPosition($item, $bnum);
        // key might not be an integer, so convert to string for $stack
        $strkey = serialize($item->key);
        if(!isset($stack[$strkey]))
          $stack[$strkey] = 0;
        if($x !== null) {
          $x = new Number($x);
          $bottom[] = [$x, $stack[$strkey]];
          $y = new Number($this->gridY($stack[$strkey] + $item->value));
          $stack[$strkey] += $item->value;

          $path->add($cmd, $x, $y);
          if($fill && $fillpath->isEmpty())
            $fillpath->add('M', $x, $y, 'L');
          else
            $fillpath->add($x, $y);

          // no need to repeat same L command
          $cmd = $cmd == 'M' ? 'L' : '';
          if($item->value !== null)
            ++$point_count;
        }
        ++$bnum;
      }

      if($point_count > 0) {
        $attr['d'] = $path;
        $attr['stroke'] = $this->getColour(null, 0, $i, false, false);
        if($this->getOption('semantic_classes'))
          $attr['class'] = 'series' . $i;
        $graph_line = $this->element('path', $attr);
        $fill_style = null;

        if($fill) {
          // complete the fill area with the previous stack total
          $cmd = 'L';
          $opacity = $this->getOption(['fill_opacity', $i]);
          $bpoints = array_reverse($bottom, TRUE);
          foreach($bpoints as $pos) {
            list($x, $ypos) = $pos;
            $y = $this->gridY($ypos);
            $fillpath->add($x, $y);
          }
          $fillpath->add('z');
          $fill_style = [
            'fill' => $this->getColour(null, 0, $i),
            'd' => $fillpath,
            'stroke' => $attr['fill'],
          ];
          if($opacity < 1)
            $fill_style['opacity'] = $opacity;
          if($this->getOption('semantic_classes'))
            $fill_style['class'] = 'series' . $i;
          $graph_line = $this->element('path', $fill_style) . $graph_line;
        }

        $plots[] = $graph_line;
        unset($attr['d'], $attr['class'], $fill_style['class']);

        // add the markers and associated legend entries
        $this->line_styles[$i] = $attr;
        $this->fill_styles[$i] = $fill_style;
        $bnum = 0;
        foreach($this->multi_graph[$i] as $item) {
          $x = $this->gridPosition($item, $bnum);
          // key might not be an integer, so convert to string for $stack
          $strkey = serialize($item->key);
          if($x !== null) {
            $y = $this->gridY($stack[$strkey]);

            if($item->value !== null) {
              $marker_id = $this->markerLabel($i, $bnum, $item, $x, $y);
              $extra = empty($marker_id) ? null : ['id' => $marker_id];
              $this->addMarker($x, $y, $item, $extra, $i);
            }
          }
          ++$bnum;
        }
      }
    }

    $plots = array_reverse($plots);
    $all_plots = implode($plots);

    $group = [];
    $this->clipGrid($group);
    if($this->getOption('semantic_classes'))
      $group['class'] = 'series';
    if(!empty($group))
      $all_plots = $this->element('g', $group, null, $all_plots);

    $group = [];
    $shadow_id = $this->defs->getShadow();
    if($shadow_id !== null)
      $group['filter'] = 'url(#' . $shadow_id . ')';
    if(!empty($group))
      $all_plots = $this->element('g', $group, null, $all_plots);

    list($best_fit_above, $best_fit_below) = $this->bestFitLines();
    $body .= $best_fit_below;
    $body .= $all_plots;
    $body .= $this->overShapes();
    $body .= $this->axes();
    $body .= $this->drawMarkers();
    $body .= $best_fit_above;
    return $body;
  }


  /**
   * Returns the maximum value
   */
  public function getMaxValue()
  {
    return $this->multi_graph->getMaxSumValue();
  }

  /**
   * Returns the minimum value
   */
  public function getMinValue()
  {
    return $this->multi_graph->getMinSumValue();
  }

  /**
   * Returns the ordering for legend entries
   */
  public function getLegendOrder()
  {
    return 'reverse';
  }
}

