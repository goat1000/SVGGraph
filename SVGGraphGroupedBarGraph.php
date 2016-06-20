<?php
/**
 * Copyright (C) 2011-2016 Graham Breach
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
require_once 'SVGGraphBarGraph.php';

class GroupedBarGraph extends BarGraph {

  protected function Draw()
  {
    $body = $this->Grid() . $this->UnderShapes();

    $chunk_count = count($this->multi_graph);
    list($chunk_width, $bspace, $chunk_unit_width) =
      GroupedBarGraph::BarPosition($this->bar_width, $this->bar_width_min,
      $this->x_axes[$this->main_x_axis]->Unit(), $chunk_count, $this->bar_space,
      $this->group_space);

    $bar_style = array();
    $bar = array('width' => $chunk_width);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);

    $bnum = 0;
    $bars = '';
    foreach($this->multi_graph as $itemlist) {
      $item = $itemlist[0];
      $k = $item->key;
      $bar_pos = $this->GridPosition($item, $bnum);
      if(!is_null($bar_pos)) {
        for($j = 0; $j < $chunk_count; ++$j) {
          $bar['x'] = $bspace + $bar_pos + ($j * $chunk_unit_width);
          $item = $itemlist[$j];
          $this->SetStroke($bar_style, $item, $j);
          $bar_style['fill'] = $this->GetColour($item, $bnum, $j);

          if(!is_null($item->value)) {
            $this->Bar($item->value, $bar, NULL, $this->DatasetYAxis($j));

            if($bar['height'] > 0) {
              $show_label = $this->AddDataLabel($j, $bnum, $bar, $item,
                $bar['x'], $bar['y'], $bar['width'], $bar['height']);
              if($this->show_tooltips)
                $this->SetTooltip($bar, $item, $j, $item->key, $item->value,
                  !$this->compat_events && $show_label);
              if($this->semantic_classes)
                $bar['class'] = "series{$j}";
              $rect = $this->Element('rect', $bar, $bar_style);
              $bars .= $this->GetLink($item, $k, $rect);
              unset($bar['id']); // clear for next generated value
            }
          }
          $this->SetLegendEntry($j, $bnum, $item, $bar_style);
        }
      }
      ++$bnum;
    }

    if($this->semantic_classes)
      $bars = $this->Element('g', array('class' => 'series'), NULL, $bars);
    $body .= $bars;
    $body .= $this->OverShapes();
    $body .= $this->Axes();
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
        $this->datetime_keys, $this->require_integer_keys);
  }

  /**
   * Calculates the bar width, gap to first bar, gap between bars
   * returns an array containing all three
   */
  static function BarPosition($bar_width, $bar_width_min, $unit_width, 
    $group_size, $bar_space, $group_space)
  {
    if(is_numeric($bar_width) && $bar_width >= 1) {
      return GroupedBarGraph::BarPositionFixed($bar_width, $unit_width,
        $group_size, $group_space);
    } else {
      // bar width dependent on space
      $gap_count = $group_size - 1;
      $gap = $gap_count > 0 ? $group_space : 0;

      $bar_width = $bar_space >= $unit_width ? '1' : $unit_width - $bar_space;
      if($gap_count > 0 && $gap * $gap_count > $bar_width - $group_size)
        $gap = ($bar_width - $group_size) / $gap_count;
      $bar_width = ($bar_width - ($gap * ($group_size - 1)))
        / $group_size;

      if($bar_width < $bar_width_min)
        return GroupedBarGraph::BarPositionFixed($bar_width_min, $unit_width,
          $group_size, $group_space);
      $spacing = $bar_width + $gap;
      $offset = $bar_space / 2;
    }
    return array($bar_width, $offset, $spacing);
  }

  /**
   * Calculate bar width, gaps, using fixed bar width
   */
  static function BarPositionFixed($bar_width, $unit_width, $group_size,
    $group_space)
  {
    $gap = $group_size > 1 ? $group_space : 0;
    if($group_size > 1 && ($bar_width + $gap) * $group_size > $unit_width) {

      // bars don't fit with group_space option, so they must overlap
      // (and make sure the bars are at least 1 pixel apart)
      $spacing = max(1, ($unit_width - $bar_width) / ($group_size - 1));
      $offset = 0;
    } else {
      // space the bars group_space apart, centred in unit space
      $spacing = $bar_width + $gap;
      $offset = max(0, ($unit_width - ($spacing * $group_size)) / 2);
    }
    return array($bar_width, $offset, $spacing);
  }

}

