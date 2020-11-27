<?php
/**
 * Copyright (C) 2011-2020 Graham Breach
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
 * MultiLineGraph - joined line, with axes and grid
 */
class MultiLineGraph extends LineGraph {

  use MultiGraphTrait;

  protected function draw()
  {
    $body = $this->grid() . $this->underShapes();

    $plots = '';
    $y_axis_pos = $this->height - $this->pad_bottom -
      $this->y_axes[$this->main_y_axis]->zero();
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);
    $datasets = $this->multi_graph->getEnabledDatasets();

    foreach($datasets as $i) {
      $bnum = 0;
      $points = [];
      $plot = '';
      $line_breaks = $this->getOption(['line_breaks', $i]);
      $axis = $this->datasetYAxis($i);
      foreach($this->multi_graph[$i] as $item) {
        if($line_breaks && $item->value === null && count($points) > 0) {
          $plot .= $this->drawLine($i, $points, $y_bottom);
          $points = [];
        } else {
          $x = $this->gridPosition($item, $bnum);
          if($x !== null && $item->value !== null) {
            $y = $this->gridY($item->value, $axis);
            $points[] = [$x, $y, $item, $i, $bnum];
          }
        }
        ++$bnum;
      }

      $plot .= $this->drawLine($i, $points, $y_bottom);
      $plots .= $plot;
    }

    $group = [];
    $this->clipGrid($group);
    if($this->semantic_classes)
      $group['class'] = 'series';
    if(!empty($group))
      $plots = $this->element('g', $group, null, $plots);

    $group = [];
    $shadow_id = $this->defs->getShadow();
    if($shadow_id !== null)
      $group['filter'] = 'url(#' . $shadow_id . ')';
    if(!empty($group))
      $plots = $this->element('g', $group, null, $plots);

    list($best_fit_above, $best_fit_below) = $this->bestFitLines();
    $body .= $best_fit_below;
    $body .= $plots;
    $body .= $this->overShapes();
    $body .= $this->axes();
    $body .= $this->drawMarkers();
    $body .= $best_fit_above;
    return $body;
  }
}

