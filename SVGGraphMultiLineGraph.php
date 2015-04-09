<?php
/**
 * Copyright (C) 2011-2015 Graham Breach
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

require_once 'SVGGraphLineGraph.php';
require_once 'SVGGraphMultiGraph.php';

/**
 * MultiLineGraph - joined line, with axes and grid
 */
class MultiLineGraph extends LineGraph {

  protected $require_integer_keys = false;

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $plots = '';
    $y_axis_pos = $this->height - $this->pad_bottom - 
      $this->y_axes[$this->main_y_axis]->Zero();
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);

    $chunk_count = count($this->multi_graph);
    $this->ColourSetup($this->multi_graph->ItemsCount(-1), $chunk_count);

    for($i = 0; $i < $chunk_count; ++$i) {
      $bnum = 0;
      $points = array();
      $axis = $this->DatasetYAxis($i);
      foreach($this->multi_graph[$i] as $item) {
        $x = $this->GridPosition($item->key, $bnum);
        if(!is_null($x) && !is_null($item->value)) {
          $y = $this->GridY($item->value, $axis);
          $points[] = array($x, $y, $item, $i, $bnum);
        }
        ++$bnum;
      }

      $plot = $this->DrawLine($i, $points, $y_bottom);
      if($this->semantic_classes)
        $plots .= $this->Element('g', array('class' => 'series'), NULL, $plot);
      else
        $plots .= $plot;
    }

    $group = array();
    $this->ClipGrid($group);

    $body .= $this->Element('g', $group, NULL, $plots);
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
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
}

