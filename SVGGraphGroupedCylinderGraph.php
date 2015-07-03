<?php
/**
 * Copyright (C) 2013-2015 Graham Breach
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
require_once 'SVGGraphCylinderGraph.php';
require_once 'SVGGraphGroupedBarGraph.php';

class GroupedCylinderGraph extends CylinderGraph {

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $chunk_count = count($this->multi_graph);
    list($chunk_width, $bspace, $chunk_unit_width) =
      GroupedBarGraph::BarPosition($this->bar_width, 
      $this->x_axes[$this->main_x_axis]->Unit(), $chunk_count, $this->bar_space,
      $this->group_space);
    $bar = array('width' => $chunk_width);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);

    $this->block_width = $chunk_width;

    // make the top ellipse, set it as a symbol for re-use
    list($this->bx, $this->by) = $this->Project(0, 0, $chunk_width);
    $top = $this->BarTop();

    $bnum = 0;

    // get the translation for the whole bar
    list($tx, $ty) = $this->Project(0, 0, $bspace);
    $all_group = array();
    if($tx || $ty)
      $all_group['transform'] = "translate($tx,$ty)";
    if($this->semantic_classes)
      $all_group['class'] = 'series';

    $bars = '';
    $group = array();
    foreach($this->multi_graph as $itemlist) {
      $k = $itemlist[0]->key;
      $bar_pos = $this->GridPosition($k, $bnum);
      if(!is_null($bar_pos)) {
        for($j = 0; $j < $chunk_count; ++$j) {
          $bar['x'] = $bspace + $bar_pos + ($j * $chunk_unit_width);
          $item = $itemlist[$j];

          if(!is_null($item->value)) {
            $bar_sections = $this->Bar3D($item, $bar, $top, $bnum, $j, NULL,
              $this->DatasetYAxis($j));
            $show_label = $this->AddDataLabel($j, $bnum, $group, $item,
              $bar['x'] + $tx, $bar['y'] + $ty, $bar['width'], $bar['height']);

            if($this->show_tooltips)
              $this->SetTooltip($group, $item, $j, $item->key, $item->value);
            $link = $this->GetLink($item, $k, $bar_sections);
            $this->SetStroke($group, $item, $j, 'round');
            if($this->semantic_classes)
              $group['class'] = "series{$j}";
            $bars .= $this->Element('g', $group, NULL, $link);
            unset($group['id'], $group['class']);
            if(!array_key_exists($j, $this->bar_styles))
              $this->bar_styles[$j] = $group;
          }
        }
      }
      ++$bnum;
    }

    if(count($all_group))
      $bars = $this->Element('g', $all_group, NULL, $bars);
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
   * Override AdjustAxes to change depth
   */
  protected function AdjustAxes(&$x_len, &$y_len)
  {
    /**
     * The depth is roughly 1/$num - but it must also take into account the
     * bar and group spacing, which is where things get messy
     */
    $ends = $this->GetAxisEnds();
    $num = $ends['k_max'][0] - $ends['k_min'][0] + 1;

    $block = $x_len / $num;
    $group = count($this->values);
    $a = $this->bar_space;
    $b = $this->group_space;
    $c = (($block) - $a - ($group - 1) * $b) / $group;
    $d = ($a + $c) / $block;
    $this->depth = $d;
    return parent::AdjustAxes($x_len, $y_len);
  }
}

