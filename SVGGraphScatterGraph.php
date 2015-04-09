<?php
/**
 * Copyright (C) 2010-2015 Graham Breach
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

require_once 'SVGGraphPointGraph.php';

/**
 * ScatterGraph - points with axes and grid
 */
class ScatterGraph extends PointGraph {

  protected $repeated_keys = 'accept';
  protected $require_integer_keys = false;

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    // a scatter graph without markers is empty!
    if($this->marker_size == 0)
      $this->marker_size = 1;
    $this->ColourSetup($this->values->ItemsCount());

    $bnum = 0;
    foreach($this->values[0] as $item) {
      $x = $this->GridPosition($item->key, $bnum);
      if(!is_null($item->value) && !is_null($x)) {
        $y = $this->GridY($item->value);
        if(!is_null($y)) {
          $marker_id = $this->MarkerLabel(0, $bnum, $item, $x, $y);
          $extra = empty($marker_id) ? NULL : array('id' => $marker_id);
          $this->AddMarker($x, $y, $item, $extra);
        }
      }
      ++$bnum;
    }

    if($this->best_fit) {
      $best_fit = is_array($this->best_fit) ? $this->best_fit[0] :
        $this->best_fit;
      $colour = is_array($this->best_fit_colour) ? $this->best_fit_colour[0] :
        $this->best_fit_colour;
      $stroke_width = is_array($this->best_fit_width) ?
        $this->best_fit_width[0] : $this->best_fit_width;
      $dash = is_array($this->best_fit_dash) ?
        $this->best_fit_dash[0] : $this->best_fit_dash;
      $body .= $this->BestFit($best_fit, 0, $colour, $stroke_width, $dash);
    }
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    return $body;
  }

  /**
   * Checks that the data produces a 2-D plot
   */
  protected function CheckValues()
  {
    parent::CheckValues();

    // using force_assoc makes things work properly
    if($this->values->AssociativeKeys())
      $this->force_assoc = true;
  }

}

