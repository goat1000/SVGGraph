<?php
/**
 * Copyright (C) 2019 Graham Breach
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

trait StackedGroupedBarTrait {

  use GroupedBarTrait;

  // stores the actual group starts
  protected $groups = [];

  // stores the group each dataset is within
  protected $dataset_groups = [];

  protected function drawBars()
  {
    $this->barSetup();
    $group_count = count($this->groups);
    $bar_count = count($this->multi_graph);
    $this->colourSetup($this->multi_graph->itemsCount(-1), $bar_count);

    $bars = '';
    foreach($this->multi_graph as $bnum => $itemlist) {
      $item = $itemlist[0];
      $k = $item->key;
      $bar_pos = $this->gridPosition($item, $bnum);

      if(!is_null($bar_pos)) {

        for($l = 0; $l < $group_count; ++$l) {
          $start_bar = $this->groups[$l];
          $end_bar = isset($this->groups[$l + 1]) ? $this->groups[$l + 1] : $bar_count;
          $ypos = $yneg = 0;

          // stack the bars in order they must be drawn
          $stack = [];
          for($j = $start_bar; $j < $end_bar; ++$j) {
            $item = $itemlist[$j];
            if(!is_null($item->value)) {
              if($item->value < 0) {
                array_unshift($stack, [$j, $yneg]);
                $yneg += $item->value;
                continue;
              }
              $stack[] = [$j, $ypos];
              $ypos += $item->value;
            }
          }

          $stack_last = count($stack) - 1;
          foreach($stack as $b => $stack_bar) {
            list($j, $start) = $stack_bar;
            $item = $itemlist[$j];
            $top = ($b == $stack_last);
            // store whether the bar can be seen or not
            $this->bar_visibility[$j][$item->key] = ($top || $item->value != 0);
            $this->setBarLegendEntry($j, $bnum, $item);
            $bars .= $this->drawBar($item, $bnum, $start, null, $j, ['top' => $top]);
          }
          $this->barTotals($item, $bnum, $ypos, $yneg, $end_bar - 1);
        }
      }
    }

    return $bars;
  }

  /**
   * Sets up bar details
   */
  protected function barSetup()
  {
    parent::barSetup();
    $group_count = count($this->groups);
    $chunk_count = count($this->multi_graph);
    list($group_width, $bspace, $group_unit_width) =
      $this->barPosition($this->bar_width, $this->bar_width_min,
      $this->x_axes[$this->main_x_axis]->unit(), $group_count, $this->bar_space,
      $this->group_space);
    $this->group_bar_spacing = $group_unit_width;
    $this->setBarWidth($group_width, $bspace);
  }

  /**
   * Fills in the x and width of bar
   */
  protected function barX($item, $index, &$bar, $axis, $dataset)
  {
    $bar_x = $this->gridPosition($item, $index);
    if(is_null($bar_x))
      return null;

    $group = $this->dataset_groups[$dataset];
    $bar['x'] = $bar_x + $this->calculated_bar_space +
        ($group * $this->group_bar_spacing);
    $bar['width'] = $this->calculated_bar_width;
    return $bar_x;
  }

  /**
   * Check that the required options are set and match the data
   */
  protected function checkValues()
  {
    parent::checkValues();
    if(empty($this->stack_group))
      throw new \Exception('stack_group not set');

    // make sure the group details are stored in an array
    if(!is_array($this->stack_group))
      $this->stack_group = [$this->stack_group];

    // make the list of groups
    $datasets = count($this->multi_graph);
    $this->groups = [0]; // first starts at 0, obviously
    $this->dataset_groups = array_fill(0, $datasets, 0);

    $last_start = 0;
    foreach($this->stack_group as $key => $group_start) {
      if($group_start <= $last_start)
        throw new \Exception('Invalid stack_group option');
      if($group_start < $datasets)
        $this->groups[] = $group_start;
      $last_start = $group_start;
      for($d = $group_start; $d < $datasets; ++$d) {
        $this->dataset_groups[$d] = $key + 1;
      }
    }

    // without this check there will be an invalid axis error
    if(count($this->groups) == 1)
      throw new \Exception('Too few datasets for grouping');
  }

  /**
   * Returns the maximum (stacked) value
   */
  public function getMaxValue()
  {
    $max = null;
    $values = &$this->multi_graph->getValues();

    // find the max for each group from the MultiGraph's structured data
    for($i = 0; $i < count($this->groups); ++$i) {
      $start = $this->groups[$i];
      $end = isset($this->groups[$i + 1]) ? $this->groups[$i + 1] - 1 : null;
      list($junk, $group_max) = $values->getMinMaxSumValues($start, $end);
      if(is_null($max) || $group_max > $max)
        $max = $group_max;
      $start = $end + 1;
    }
    return $max;
  }

  /**
   * Returns the minimum (stacked) value
   */
  public function getMinValue()
  {
    $min = null;
    $values = &$this->multi_graph->getValues();

    // find the min for each group from the MultiGraph's structured data
    for($i = 0; $i < count($this->groups); ++$i) {
      $start = $this->groups[$i];
      $end = isset($this->groups[$i + 1]) ? $this->groups[$i + 1] - 1 : null;
      list($group_min) = $values->getMinMaxSumValues($start, $end);
      if(is_null($min) || $group_min < $min)
        $min = $group_min;
      $start = $end + 1;
    }
    return $min;
  }
}

