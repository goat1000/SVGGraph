<?php
/**
 * Copyright (C) 2010-2019 Graham Breach
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
 * ScatterGraph - points with axes and grid
 */
class ScatterGraph extends PointGraph {

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
    if($this->marker_size == 0)
      $this->marker_size = 1;
    $this->colourSetup($this->values->itemsCount());

    $bnum = 0;
    foreach($this->values[0] as $item) {
      $x = $this->gridPosition($item, $bnum);
      if(!is_null($item->value) && !is_null($x)) {
        $y = $this->gridY($item->value);
        if(!is_null($y)) {
          $marker_id = $this->markerLabel(0, $bnum, $item, $x, $y);
          $extra = empty($marker_id) ? null : ['id' => $marker_id];
          $this->addMarker($x, $y, $item, $extra);
        }
      }
      ++$bnum;
    }

    list($best_fit_above, $best_fit_below) = $this->bestFitLines();
    $body .= $best_fit_below;
    $body .= $this->overShapes();
    $body .= $this->axes();
    $body .= $this->crossHairs();
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

