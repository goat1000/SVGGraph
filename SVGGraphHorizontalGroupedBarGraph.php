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
require_once 'SVGGraphHorizontalBarGraph.php';
require_once 'SVGGraphGroupedBarGraph.php';

class HorizontalGroupedBarGraph extends HorizontalBarGraph {

  protected $legend_reverse = true;
  protected $single_axis = true;

  protected function Draw()
  {
    $body = $this->Grid() . $this->UnderShapes();

    $chunk_count = count($this->multi_graph);
    list($chunk_height, $bspace, $chunk_unit_height) =
      GroupedBarGraph::BarPosition($this->bar_width, $this->bar_width_min,
      $this->y_axes[$this->main_y_axis]->Unit(), $chunk_count, $this->bar_space,
      $this->group_space);
    $bar_style = array();
    $bar = array('height' => $chunk_height);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);

    $bnum = 0;
    $bars = '';
    foreach($this->multi_graph as $itemlist) {
      $item = $itemlist[0];
      $k = $item->key;
      $bar_pos = $this->GridPosition($item, $bnum);
      if(!is_null($bar_pos)) {
        for($j = 0; $j < $chunk_count; ++$j) {
          $bar['y'] = $bar_pos - $bspace - $chunk_height - 
            ($j * $chunk_unit_height);
          $item = $itemlist[$j];
          $this->SetStroke($bar_style, $item, $j);
          $bar_style['fill'] = $this->GetColour($item, $bnum, $j);
          $this->Bar($item->value, $bar);

          if($bar['width'] > 0) {
            $show_label = $this->AddDataLabel($j, $bnum, $bar, $item,
              $bar['x'], $bar['y'], $bar['width'], $bar['height']);
            if($this->show_tooltips)
              $this->SetTooltip($bar, $item, $j, $item->key, $item->value,
                !$this->compat_events && $show_label);
            if($this->semantic_classes)
              $bar['class'] = "series{$j}";
            $rect = $this->Element('rect', $bar, $bar_style);
            $bars .= $this->GetLink($item, $k, $rect);
            unset($bar['id']); // clear ID for next generated value
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
}

