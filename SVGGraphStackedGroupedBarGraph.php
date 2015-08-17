<?php
/**
 * Copyright (C) 2014-2015 Graham Breach
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
require_once 'SVGGraphGroupedBarGraph.php'; // for BarPosition()
require_once 'SVGGraphData.php';

class StackedGroupedBarGraph extends StackedBarGraph {

  protected $single_axis = true;

  // stores the actual group starts
  protected $groups = array();

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by StackedGroupedBarGraph');

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $group_count = count($this->groups);
    list($group_width, $bspace, $group_unit_width) =
      GroupedBarGraph::BarPosition($this->bar_width, 
      $this->x_axes[$this->main_x_axis]->Unit(), $group_count, $this->bar_space,
      $this->group_space);
    $bar_style = array();
    $bar = array('width' => $group_width);

    $bnum = 0;
    $bar_count = count($this->multi_graph);
    $bars_shown = array_fill(0, $bar_count, 0); // for legend yes/no
    $bars = '';
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $bar_count);

    foreach($this->multi_graph as $itemlist) {
      $k = $itemlist[0]->key;
      $bar_pos = $this->GridPosition($k, $bnum);

      if(!is_null($bar_pos)) {

        for($l = 0; $l < $group_count; ++$l) {
          $bar['x'] = $bspace + $bar_pos + ($l * $group_unit_width);
          $start_bar = $this->groups[$l];
          $end_bar = isset($this->groups[$l + 1]) ? $this->groups[$l + 1] : $bar_count;

          $ypos = $yneg = 0;

          // find greatest -/+ bar
          $max_neg_bar = $max_pos_bar = -1;
          for($j = $start_bar; $j < $end_bar; ++$j) {
            if($itemlist[$j]->value > 0)
              $max_pos_bar = $j;
            else
              $max_neg_bar = $j;
          }
          for($j = $start_bar; $j < $end_bar; ++$j) {
            $item = $itemlist[$j];
            $this->SetStroke($bar_style, $item, $j);
            $bar_style['fill'] = $this->GetColour($item, $bnum, $j);

            if(!is_null($item->value)) {
              $this->Bar($item->value, $bar, $item->value >= 0 ? $ypos : $yneg);
              if($item->value < 0)
                $yneg += $item->value;
              else
                $ypos += $item->value;

              if($bar['height'] > 0) {
                ++$bars_shown[$j];

                $show_label = $this->AddDataLabel($j, $bnum, $bar, $item,
                  $bar['x'], $bar['y'], $bar['width'], $bar['height']);
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
            $this->bar_styles[$j] = $bar_style;
          }
          if($this->show_bar_totals) {
            if($ypos) {
              // make a dataset name for stack total
              $tds = 'totalpos-' . $start_bar;
              $this->Bar($ypos, $bar);
              if(is_callable($this->bar_total_callback))
                $bar_total = call_user_func($this->bar_total_callback, $item->key,
                  $ypos);
              else
                $bar_total = $ypos;
              $this->AddContentLabel($tds, $bnum, $bar['x'], $bar['y'],
                $bar['width'], $bar['height'], $bar_total);
            }
            if($yneg) {
              $tds = 'totalneg-' . $start_bar;
              $this->Bar($yneg, $bar);
              if(is_callable($this->bar_total_callback))
                $bar_total = call_user_func($this->bar_total_callback, $item->key,
                  $yneg);
              else
                $bar_total = $yneg;
              $this->AddContentLabel($tds, $bnum, $bar['x'], $bar['y'],
                $bar['width'], $bar['height'], $bar_total);
            }
          }
        }
      }
      ++$bnum;
    }
    if(!$this->legend_show_empty) {
      foreach($bars_shown as $j => $bar) {
        if(!$bar)
          $this->bar_styles[$j] = NULL;
      }
    }

    if($this->semantic_classes)
      $bars = $this->Element('g', array('class' => 'series'), NULL, $bars);
    $body .= $bars;
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * construct multigraph
   */
  public function Values($values)
  {
    parent::Values($values);
    if(!$this->values->error)
      $this->multi_graph = new MultiGraph($this->values, $this->force_assoc,
        $this->require_integer_keys);
  }

  /**
   * Check that the required options are set and match the data
   */
  protected function CheckValues()
  {
    parent::CheckValues();
    if(empty($this->stack_group))
      throw new Exception('stack_group not set for StackedGroupedBarGraph');

    // make sure the group details are stored in an array
    if(!is_array($this->stack_group))
      $this->stack_group = array($this->stack_group);

    // make the list of groups
    $datasets = count($this->multi_graph);
    $this->groups = array(0); // first starts at 0, obviously

    $last_start = 0;
    foreach($this->stack_group as $group_start) {
      if($group_start <= $last_start)
        throw new Exception('Invalid stack_group option');
      if($group_start < $datasets)
        $this->groups[] = $group_start;
      $last_start = $group_start;
    }

    // without this check there will be an invalid axis error
    if(count($this->groups) == 1)
      throw new Exception('Too few datasets for grouping');
  }

  /**
   * Returns the maximum (stacked) value
   */
  protected function GetMaxValue()
  {
    $max = NULL;
    $values = &$this->multi_graph->GetValues();

    // find the max for each group from the MultiGraph's structured data
    for($i = 0; $i < count($this->groups); ++$i) {
      $start = $this->groups[$i];
      $end = isset($this->groups[$i + 1]) ? $this->groups[$i + 1] - 1 : NULL;
      list($junk, $group_max) = $values->GetMinMaxSumValues($start, $end);
      if(is_null($max) || $group_max > $max)
        $max = $group_max;
      $start = $end + 1;
    }
    return $max;
  }

  /**
   * Returns the minimum (stacked) value
   */
  protected function GetMinValue()
  {
    $min = NULL;
    $values = &$this->multi_graph->GetValues();

    // find the min for each group from the MultiGraph's structured data
    for($i = 0; $i < count($this->groups); ++$i) {
      $start = $this->groups[$i];
      $end = isset($this->groups[$i + 1]) ? $this->groups[$i + 1] - 1 : NULL;
      list($group_min) = $values->GetMinMaxSumValues($start, $end);
      if(is_null($min) || $group_min < $min)
        $min = $group_min;
      $start = $end + 1;
    }
    return $min;
  }

}

