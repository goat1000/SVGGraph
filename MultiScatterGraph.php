<?php
/**
 * Copyright (C) 2011-2022 Graham Breach
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
 * MultiScatterGraph - points with axes and grid
 */
class MultiScatterGraph extends PointGraph {

  use MultiGraphTrait;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = [
      'repeated_keys' => 'accept',
      'require_integer_keys' => false,
    ];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  protected function draw()
  {
    $body = $this->grid() . $this->underShapes();

    // a scatter graph without markers is empty!
    if($this->getOption('marker_size') == 0)
      $this->setOption('marker_size', 1);

    $datasets = $this->multi_graph->getEnabledDatasets();
    foreach($datasets as $i) {
      $bnum = 0;
      $axis = $this->datasetYAxis($i);
      foreach($this->multi_graph[$i] as $item) {
        $x = $this->gridPosition($item, $bnum);
        if($item->value !== null && $x !== null) {
          $y = $this->gridY($item->value, $axis);
          if($y !== null) {
            $marker_id = $this->markerLabel($i, $bnum, $item, $x, $y);
            $extra = empty($marker_id) ? null : ['id' => $marker_id];
            $this->addMarker($x, $y, $item, $extra, $i);
          }
        }
        ++$bnum;
      }
    }

    list($best_fit_above, $best_fit_below) = $this->bestFitLines();
    $body .= $best_fit_below;
    $body .= $this->overShapes();
    $body .= $this->axes();
    $body .= $this->drawMarkers();
    $body .= $best_fit_above;
    return $body;
  }

  /**
   * Checks that the data produces a 2-D plot
   */
  protected function checkValues()
  {
    parent::checkValues();

    // using force_assoc makes things work properly
    if($this->values->associativeKeys())
      $this->setOption('force_assoc', true);
  }
}

