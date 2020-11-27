<?php
/**
 * Copyright (C) 2017-2020 Graham Breach
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

class StackedBarAndLineGraph extends StackedBarGraph {

  protected $linegraph = null;
  protected $dataset_types = [];

  /**
   * We need an instance of the LineGraph class
   */
  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fixed = ['single_axis' => false];
    $fixed = array_merge($fixed, $fixed_settings);
    parent::__construct($w, $h, $settings, $fixed);

    // prevent repeated labels
    unset($settings['label']);
    $this->linegraph = new MultiLineGraph($w, $h, $settings);

    // validate secondary axis datasets are only lines
    if(isset($settings['dataset_axis'])) {
      if(!is_array($settings['dataset_axis'])) {
        $this->setOption('dataset_axis', null);
        return;
      }

      $lines = is_array($settings['line_dataset']) ? $settings['line_dataset'] :
        [$settings['line_dataset']];

      $line_map = [];
      foreach($lines as $line)
        $line_map[$line] = 1;
      foreach($settings['dataset_axis'] as $dataset => $axis) {
        if($axis != 0 && !isset($line_map[$dataset])) {
          throw new \Exception('Bar datasets must use axis 0');
        }
      }
    }
  }

  /**
   * Draws the bars and lines
   */
  protected function draw()
  {
    if($this->log_axis_y)
      throw new \Exception('log_axis_y not supported by StackedBarAndLineGraph');
    $body = $this->grid() . $this->underShapes();

    // LineGraph has not been initialised, need to copy in details
    $copy = ['colours', 'links', 'x_axes', 'y_axes', 'main_x_axis',
      'main_y_axis', 'legend'];
    foreach($copy as $member)
      $this->linegraph->{$member} = $this->{$member};

    // keep gradients and patterns synced
    $this->linegraph->defs =& $this->defs;

    // find the lines
    $lines = $this->getOption('line_dataset');
    $line_breaks = [];
    $line_points = [];
    $points = [];
    if(!is_array($lines))
      $lines = [$lines];
    rsort($lines);
    foreach($lines as $line) {
      $line_breaks[$line] = $this->getOption(['line_breaks', $line]);
      $line_points[$line] = [];
      $points[$line] = [];
    }
    $lines = array_flip($lines);

    $y_axis_pos = $this->height - $this->pad_bottom -
      $this->y_axes[$this->main_y_axis]->zero();
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);

    $this->barSetup();
    $marker_offset = $this->x_axes[$this->main_x_axis]->unit() / 2;
    $datasets = $this->multi_graph->getEnabledDatasets();

    // draw bars, store line points
    $line_dataset = 0;
    $bars = '';
    $legend_entries = [];
    foreach($this->multi_graph as $bnum => $itemlist) {
      $item = $itemlist[0];
      $bar_pos = $this->gridPosition($item, $bnum);

      if($bar_pos !== null) {

        $yplus = $yminus = 0;
        $chunk_values = [];
        foreach($datasets as $j) {
          $y_axis = $this->datasetYAxis($j);
          $item = $itemlist[$j];

          if(array_key_exists($j, $lines)) {
            $line_dataset = $j;
            $this->dataset_types[$j] = 'l';
            if($line_breaks[$line_dataset] && $item->value === null &&
              count($points[$line_dataset]) > 0) {
              $line_points[$line_dataset][] = $points[$line_dataset];
              $points[$line_dataset] = [];
            } elseif($item->value !== null) {
              $x = $bar_pos + $marker_offset;
              $y = $this->gridY($item->value, $y_axis);
              $points[$line_dataset][] = [$x, $y, $item, $line_dataset, $bnum];
              $this->bar_visibility[$line_dataset][$item->key] = 1;
            }
            continue;
          }

          $this->dataset_types[$j] = 'b';
          if($item->value !== null) {
            // sort the values from bottom to top, assigning position
            if($item->value < 0) {
              array_unshift($chunk_values, [$j, $item, $yminus]);
              $yminus += $item->value;
            } else {
              $chunk_values[] = [$j, $item, $yplus];
              $yplus += $item->value;
            }
          }
        }

        $bar_count = count($chunk_values);
        $b = 0;
        foreach($chunk_values as $chunk) {
          list($j, $item, $start) = $chunk;

          // store whether the bar can be seen or not
          $top = (++$b == $bar_count);
          $this->bar_visibility[$j][$item->key] = ($top || $item->value != 0);

          $legend_entries[$j][$bnum] = $item;
          $bars .= $this->drawBar($item, $bnum, $start, null, $j, ['top' => $top]);
        }

        $this->barTotals($item, $bnum, $yplus, $yminus, $j);
      }
    }

    foreach($legend_entries as $j => $dataset)
      foreach($dataset as $bnum => $item)
        $this->setBarLegendEntry($j, $bnum, $item);

    foreach($points as $line_dataset => $line) {
      if(!empty($line))
        $line_points[$line_dataset][] = $line;
    }

    // draw lines clipped to grid
    $graph_line = '';
    foreach($line_points as $dataset => $points) {
      foreach($points as $p) {
        $graph_line .= $this->linegraph->drawLine($dataset, $p, $y_bottom);
      }
    }
    $group = [];
    $this->clipGrid($group);
    $bars .= $this->element('g', $group, null, $graph_line);

    $group = [];
    if($this->semantic_classes)
      $group['class'] = 'series';
    $shadow_id = $this->defs->getShadow();
    if($shadow_id !== null)
      $group['filter'] = 'url(#' . $shadow_id . ')';
    if(!empty($group))
      $bars = $this->element('g', $group, null, $bars);
    $body .= $bars;

    $body .= $this->overShapes();
    $body .= $this->axes();

    // add in the markers created by line graph
    $body .= $this->linegraph->drawMarkers();

    return $body;
  }

  /**
   * Return box or line for legend
   */
  public function drawLegendEntry($x, $y, $w, $h, $entry)
  {
    if(isset($entry->style['line_style']))
      return $this->linegraph->drawLegendEntry($x, $y, $w, $h, $entry);
    return parent::drawLegendEntry($x, $y, $w, $h, $entry);
  }

  /**
   * Draws this graph's data labels, and the line graph's data labels
   */
  protected function drawDataLabels()
  {
    $labels = parent::drawDataLabels();
    $labels .= $this->linegraph->drawDataLabels();
    return $labels;
  }

  /**
   * Returns the minimum value for an axis
   */
  protected function getAxisMinValue($axis)
  {
    if($this->min_values === null)
      $this->calcMinMaxValues();
    return isset($this->min_values[$axis]) ? $this->min_values[$axis] : null;
  }

  /**
   * Returns the maximum value for an axis
   */
  protected function getAxisMaxValue($axis)
  {
    if($this->max_values === null)
      $this->calcMinMaxValues();
    return isset($this->max_values[$axis]) ? $this->max_values[$axis] : null;
  }

  /**
   * Finds the minimum and maximum stack or line
   */
  private function calcMinMaxValues()
  {
    $lines = $this->line_dataset;
    if(!is_array($lines))
      $lines = [$lines];
    sort($lines);
    $lines = array_flip($lines);

    $axis_count = $this->yAxisCount();
    $axis_max = array_fill(0, $axis_count, null);
    $axis_min = array_fill(0, $axis_count, null);
    $stack_max = null;
    $stack_min = null;
    $datasets = $this->multi_graph->getEnabledDatasets();
    $bar_datasets = 0;
    foreach($datasets as $j) {
      if(!array_key_exists($j, $lines))
        ++$bar_datasets;
    }
    if($bar_datasets === 0)
      throw new \Exception('No bar datasets enabled');

    foreach($this->multi_graph as $itemlist) {

      $stack_pos = $stack_neg = 0;

      foreach($datasets as $j) {
        $item = $itemlist[$j];
        if($item->value === null)
          continue;
        if(!is_numeric($item->value))
          throw new \Exception('Non-numeric value');

        if(array_key_exists($j, $lines)) {
          // for lines  find the global min/max for each axis
          $axis = $this->datasetYAxis($j);
          if($axis_min[$axis] === null || $axis_min[$axis] > $item->value)
            $axis_min[$axis] = $item->value;
          if($axis_max[$axis] === null || $axis_max[$axis] < $item->value)
            $axis_max[$axis] = $item->value;
        } else {

          // for bars need to find min and max stack sizes, using positive
          // and negative stacks
          if($item->value < 0)
            $stack_neg += $item->value;
          else
            $stack_pos += $item->value;
        }
      }

      if($stack_min === null || $stack_neg < $stack_min)
        $stack_min = $stack_neg;
      if($stack_max === null || $stack_neg > $stack_max)
        $stack_max = $stack_neg;

      if($stack_min === null || $stack_pos < $stack_min)
        $stack_min = $stack_pos;
      if($stack_max === null || $stack_pos > $stack_max)
        $stack_max = $stack_pos;
    }

    if($axis_min[0] === null || $stack_min < $axis_min[0])
      $axis_min[0] = $stack_min;

    if($axis_max[0] === null || $stack_max > $axis_max[0])
      $axis_max[0] = $stack_max;

    $this->min_values = $axis_min;
    $this->max_values = $axis_max;
  }

  /**
   * Returns the order that the datasets should appear in
   */
  public function getLegendOrder()
  {
    $stack = [];
    $lines = [];
    foreach($this->dataset_types as $d => $t)
      if($t == 'l')
        $lines[] = $d;
      else
        array_unshift($stack, $d);

    return array_merge($stack, $lines);
  }
}

