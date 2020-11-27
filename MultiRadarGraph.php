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
 * MultiRadarGraph - multiple radar graphs on one plot
 */
class MultiRadarGraph extends RadarGraph {

  use MultiGraphTrait;

  protected function draw()
  {
    $body = $this->grid() . $this->underShapes();
    $plots = '';

    $datasets = $this->multi_graph->getEnabledDatasets();
    foreach($datasets as $i) {
      $bnum = 0;
      $points = [];
      $plot = '';
      $line_breaks = $this->getOption(['line_breaks', $i]);
      $y_axis = $this->y_axes[$this->main_y_axis];
      $first_point = null;
      foreach($this->multi_graph[$i] as $item) {
        if($line_breaks && $item->value === null && count($points) > 0) {
          $plot .= $this->drawLine($i, $points, 0);
          $points = [];
        } else {
          $point_pos = $this->gridPosition($item, $bnum);
          if($item->value !== null && $point_pos !== null) {
            $val = $y_axis->position($item->value);
            $angle = $this->arad + $point_pos / $this->g_height;
            $x = $this->xc + ($val * sin($angle));
            $y = $this->yc + ($val * cos($angle));
            $points[] = [$x, $y, $item, $i, $bnum];
            if($first_point === null)
              $first_point = $points[0];
          }
        }
        ++$bnum;
      }

      // close graph or segment?
      if($first_point && (!$line_breaks || $first_point[4] == 0)) {
        $first_point[2] = null;
        $points[] = $first_point;
      }

      $plot .= $this->drawLine($i, $points, 0);
      if($this->semantic_classes)
        $plots .= $this->element('g', ['class' => 'series'], null, $plot);
      else
        $plots .= $plot;
    }

    $group = [];
    $this->clipGrid($group);
    if($this->semantic_classes)
      $group['class'] = 'series';
    $plots = $this->element('g', $group, null, $plots);

    $group = [];
    $shadow_id = $this->defs->getShadow();
    if($shadow_id !== null)
      $group['filter'] = 'url(#' . $shadow_id . ')';
    if(!empty($group))
      $plots = $this->element('g', $group, null, $plots);

    $body .= $plots;
    $body .= $this->overShapes();
    $body .= $this->axes();
    $body .= $this->drawMarkers();
    return $body;
  }
}

