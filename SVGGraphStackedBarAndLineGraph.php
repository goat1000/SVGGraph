<?php
/**
 * Copyright (C) 2017 Graham Breach
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

require_once 'SVGGraphMultiGraph.php';
require_once 'SVGGraphStackedBarGraph.php';
require_once 'SVGGraphLineGraph.php';

class StackedBarAndLineGraph extends StackedBarGraph {

  protected $linegraph = null;
  protected $single_axis = false;

  /**
   * We need an instance of the LineGraph class
   */
  public function __construct($w, $h, $settings = NULL)
  {
    parent::__construct($w, $h, $settings);

    // prevent repeated labels
    unset($settings['label']);
    $this->linegraph = new LineGraph($w, $h, $settings);

    // validate second axis datasets are only lines
    if(isset($settings['dataset_axis'])) {
      $lines = is_array($settings['line_dataset']) ? $settings['line_dataset'] :
        array($settings['line_dataset']);

      $line_map = array();
      foreach($lines as $line)
        $line_map[$line] = 1;
      foreach($settings['dataset_axis'] as $dataset => $axis) {
        if($axis == 1 && !isset($line_map[$dataset])) {
          throw new Exception('Bar datasets must use axis 0');
        }
      }
    }
  }

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by StackedBarAndLineGraph');
    $body = $this->Grid() . $this->UnderShapes();

    // LineGraph has not been initialised, need to copy in details
    $copy = array('colours', 'links', 'x_axes', 'y_axes', 'main_x_axis', 
      'main_y_axis', 'legend');
    foreach($copy as $member)
      $this->linegraph->{$member} = $this->{$member};

    // keep gradients and patterns synced
    $this->linegraph->gradients =& $this->gradients;
    $this->linegraph->pattern_list =& $this->pattern_list;
    $this->linegraph->defs =& $this->defs;

    $bar_style = array();
    $bar_width = $this->BarWidth();
    $bspace = max(0, ($this->x_axes[$this->main_x_axis]->Unit() - $bar_width) / 2);
    $bar = array('width' => $bar_width);

    $bnum = 0;
    $chunk_count = count($this->multi_graph);
    // find the lines
    $lines = $this->line_dataset;
    if(!is_array($lines))
      $lines = array($lines);
    rsort($lines);
    $lines = array_flip($lines);

    $y_axis_pos = $this->height - $this->pad_bottom - 
      $this->y_axes[$this->main_y_axis]->Zero();
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);

    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);
    $marker_offset = $this->x_axes[$this->main_x_axis]->Unit() / 2;

    $bnum = 0;
    $bars_shown = array_fill(0, $chunk_count, 0);
    $bars = '';

    // draw bars, store line points
    $points = array();
    foreach($this->multi_graph as $itemlist) {
      $item = $itemlist[0];
      $k = $item->key;
      $bar_pos = $this->GridPosition($item, $bnum);

      if(!is_null($bar_pos)) {
        $bar['x'] = $bspace + $bar_pos;
        $ypos = $yneg = 0;

        for($j = 0; $j < $chunk_count; ++$j) {
          $y_axis = $this->DatasetYAxis($j);
          $item = $itemlist[$j];

          if(array_key_exists($j, $lines)) {
            if(!is_null($item->value)) {
              $x = $bar_pos + $marker_offset;
              $y = $this->GridY($item->value, $y_axis);
              $points[$j][] = array($x, $y, $item, $j, $bnum);
            }
            continue;
          }
          $this->SetStroke($bar_style, $item, $j);
          $bar_style['fill'] = $this->GetColour($item, $bnum, $j);

          if(!is_null($item->value)) {
            $this->Bar($item->value, $bar, $item->value >= 0 ? $ypos : $yneg);
            if($item->value < 0)
              $yneg += $item->value;
            else
              $ypos += $item->value;

            if($bar['height'] > 0 || $this->show_data_labels) {
              $show_label = $this->AddDataLabel($j, $bnum, $bar, $item, $bar['x'],
                $bar['y'], $bar['width'], $bar['height']);
              if($this->show_tooltips)
                $this->SetTooltip($bar, $item, $j, $item->key, $item->value,
                  !$this->compat_events && $show_label);
              if($this->semantic_classes)
                $bar['class'] = "series{$j}";
              $rect = $this->Element('rect', $bar, $bar_style);
              $bars .= $this->GetLink($item, $k, $rect);
              unset($bar['id']); // clear for next value
            }
          }
          $this->SetLegendEntry($j, $bnum, $item, $bar_style);
        }
        if($this->show_bar_totals) {
          if($ypos) {
            $this->Bar($ypos, $bar);
            if(is_callable($this->bar_total_callback))
              $bar_total = call_user_func($this->bar_total_callback, $item->key,
                $ypos);
            else
              $bar_total = $ypos;
            $this->AddContentLabel('totalpos', $bnum, $bar['x'], $bar['y'],
              $bar['width'], $bar['height'], $bar_total);
          }
          if($yneg) {
            $this->Bar($yneg, $bar);
            if(is_callable($this->bar_total_callback))
              $bar_total = call_user_func($this->bar_total_callback, $item->key,
                $yneg);
            else
              $bar_total = $yneg;
            $this->AddContentLabel('totalneg', $bnum, $bar['x'], $bar['y'],
              $bar['width'], $bar['height'], $bar_total);
          }
        }
      }
      ++$bnum;
    }

    // draw lines clipped to grid
    $graph_line = '';
    foreach($points as $dataset => $p)
      $graph_line .= $this->linegraph->DrawLine($dataset, $p, $y_bottom);
    $group = array();
    $this->ClipGrid($group);
    $bars .= $this->Element('g', $group, NULL, $graph_line);

    if($this->semantic_classes)
      $bars = $this->Element('g', array('class' => 'series'), NULL, $bars);
    $body .= $bars;

    $body .= $this->OverShapes();
    $body .= $this->Axes();

    // add in the markers created by line graph
    $body .= $this->linegraph->DrawMarkers();

    return $body;
  }

  /**
   * Return box or line for legend
   */
  public function DrawLegendEntry($x, $y, $w, $h, $entry)
  {
    if(isset($entry->style['line_style']))
      return $this->linegraph->DrawLegendEntry($x, $y, $w, $h, $entry);
    return parent::DrawLegendEntry($x, $y, $w, $h, $entry);
  }

  /**
   * Draws this graph's data labels, and the line graph's data labels
   */
  protected function DrawDataLabels()
  {
    $labels = parent::DrawDataLabels();
    $labels .= $this->linegraph->DrawDataLabels();
    return $labels;
  }

  /**
   * Returns the minimum value for an axis
   */
  protected function GetAxisMinValue($axis)
  {
    if(is_null($this->min_values))
      $this->CalcMinMaxValues();
    return isset($this->min_values[$axis]) ? $this->min_values[$axis] : NULL;
  }

  /**
   * Returns the maximum value for an axis
   */
  protected function GetAxisMaxValue($axis)
  {
    if(is_null($this->max_values))
      $this->CalcMinMaxValues();
    return isset($this->max_values[$axis]) ? $this->max_values[$axis] : NULL;
  }

  /**
   * Finds the minimum and maximum stack or line
   */
  private function CalcMinMaxValues()
  {
    $lines = $this->line_dataset;
    if(!is_array($lines))
      $lines = array($lines);
    sort($lines);
    $lines = array_flip($lines);

    $axis_max = array(NULL, NULL);
    $axis_min = array(NULL, NULL);
    $stack_max = NULL;
    $stack_min = NULL;
    $datasets = count($this->multi_graph);

    foreach($this->multi_graph as $itemlist) {

      $stack_pos = $stack_neg = 0;

      for($j = 0; $j < $datasets; ++$j) {
        $item = $itemlist[$j];
        if(is_null($item->value))
          continue;
        if(!is_numeric($item->value))
          throw new Exception('Non-numeric value');

        if(array_key_exists($j, $lines)) {
          // for lines  find the global min/max for each axis
          $axis = $this->DatasetYAxis($j);
          if(is_null($axis_min[$axis]) || $axis_min[$axis] > $item->value)
            $axis_min[$axis] = $item->value;
          if(is_null($axis_max[$axis]) || $axis_max[$axis] < $item->value)
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

      if(is_null($stack_min) || $stack_neg < $stack_min)
        $stack_min = $stack_neg;
      if(is_null($stack_max) || $stack_neg > $stack_max)
        $stack_max = $stack_neg;

      if(is_null($stack_min) || $stack_pos < $stack_min)
        $stack_min = $stack_pos;
      if(is_null($stack_max) || $stack_pos > $stack_max)
        $stack_max = $stack_pos;
    }

    if(is_null($axis_min[0]) || $stack_min < $axis_min[0])
      $axis_min[0] = $stack_min;

    if(is_null($axis_max[0]) || $stack_max > $axis_max[0])
      $axis_max[0] = $stack_max;

    $this->min_values = $axis_min;
    $this->max_values = $axis_max;
  }

}

