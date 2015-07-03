<?php
/**
 * Copyright (C) 2015 Graham Breach
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
require_once 'SVGGraphGroupedBarGraph.php';
require_once 'SVGGraphLineGraph.php';

class BarAndLineGraph extends GroupedBarGraph {

  protected $linegraph = null;

  /**
   * We need an instance of the LineGraph class
   */
  public function __construct($w, $h, $settings = NULL)
  {
    parent::__construct($w, $h, $settings);
    $this->linegraph = new LineGraph($w, $h, $settings);
  }

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    // LineGraph has not been initialised, need to copy in details
    $copy = array('colours', 'links', 'x_axes', 'y_axes', 'main_x_axis', 
      'main_y_axis');
    foreach($copy as $member)
      $this->linegraph->{$member} = $this->{$member};

    // keep gradients and patterns synced
    $this->linegraph->gradients =& $this->gradients;
    $this->linegraph->pattern_list =& $this->pattern_list;
    $this->linegraph->defs =& $this->defs;

    // find the lines and reduce the bar count by the number of lines
    $bar_count = $chunk_count = count($this->multi_graph);
    $lines = $this->line_dataset;
    if(!is_array($lines))
      $lines = array($lines);
    rsort($lines);
    foreach($lines as $line)
      if($line < $bar_count)
        --$bar_count;
    $lines = array_flip($lines);

    $y_axis_pos = $this->height - $this->pad_bottom - 
      $this->y_axes[$this->main_y_axis]->Zero();
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);

    if($bar_count == 0) {
      $chunk_width = $bspace = $chunk_unit_width = 1;
    } else {
      // this would have problems if there are no bars
      list($chunk_width, $bspace, $chunk_unit_width) =
        GroupedBarGraph::BarPosition($this->bar_width, 
        $this->x_axes[$this->main_x_axis]->Unit(), $bar_count, $this->bar_space,
        $this->group_space);
    }

    $bar_style = array();
    $bar = array('width' => $chunk_width);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);
    $marker_offset = $this->x_axes[$this->main_x_axis]->Unit() / 2;

    $bnum = 0;
    $bars_shown = array_fill(0, $chunk_count, 0);
    $bars = '';

    // draw bars, store line points
    $points = array();
    foreach($this->multi_graph as $itemlist) {
      $k = $itemlist[0]->key;
      $bar_pos = $this->GridPosition($k, $bnum);
      if(!is_null($bar_pos)) {
        for($j = 0, $b = 0; $j < $chunk_count; ++$j) {
          $y_axis = $this->DatasetYAxis($j);
          $item = $itemlist[$j];

          if(array_key_exists($j, $lines)) {
            if(!is_null($item->value)) {
              $x = $bar_pos + $marker_offset;
              $y = $this->GridY($item->value, $y_axis);
              $points[$j][] = array($x, $y, $item, $j, $bnum);
            }
          } else {

            $bar['x'] = $bspace + $bar_pos + ($b * $chunk_unit_width);
            $this->SetStroke($bar_style, $item, $j);
            $bar_style['fill'] = $this->GetColour($item, $bnum, $j);

            if(!is_null($item->value)) {
              $this->Bar($item->value, $bar, NULL, $y_axis);

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
                unset($bar['id']); // clear for next generated value
              }
              ++$b;
            }
            $this->bar_styles[$j] = $bar_style;
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

    if(!$this->legend_show_empty) {
      foreach($bars_shown as $j => $bar) {
        if(!$bar)
          $this->bar_styles[$j] = NULL;
      }
    }

    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();

    // add in the markers created by line graph
    $body .= $this->linegraph->DrawMarkers();

    return $body;
  }

  /**
   * Return box or line for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(isset($this->bar_styles[$set]))
      return parent::DrawLegendEntry($set, $x, $y, $w, $h);

    return $this->linegraph->DrawLegendEntry($set, $x, $y, $w, $h);
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
}

