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

    $best_fit_above = $best_fit_below = '';
    if($this->best_fit) {
      $bftype = $this->ArrayOption($this->best_fit, 0);
      $colour = $this->ArrayOption($this->best_fit_colour, 0);
      $stroke_width = $this->ArrayOption($this->best_fit_width, 0);
      $dash = $this->ArrayOption($this->best_fit_dash, 0);
      $opacity = $this->ArrayOption($this->best_fit_opacity, 0);
      $best_fit = $this->BestFit($bftype, 0, $colour, $stroke_width, $dash,
        $opacity);
      if($this->semantic_classes)
        $best_fit = $this->Element('g', array('class' => 'bestfit'), NULL,
          $best_fit);
      if($this->ArrayOption($this->best_fit_above, 0))
        $best_fit_above = $best_fit;
      else
        $best_fit_below = $best_fit;
    }
    $body .= $best_fit_below;
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    $body .= $best_fit_above;
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

