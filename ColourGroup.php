<?php
/**
 * Copyright (C) 2020-2022 Graham Breach
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
 * Class for related stroke and fill colours
 */
class ColourGroup {
  private $stroke;

  public function __construct(&$graph, $item, $key, $dataset,
    $stroke_opt = 'stroke_colour', $fill = null, $item_opt = null,
    $stroke_opt_is_colour = false)
  {
    $stroke = $stroke_opt_is_colour ? $stroke_opt :
      $graph->getItemOption($stroke_opt, $dataset, $item, $item_opt);
    if(is_array($stroke)) {
      $this->stroke = new Colour($graph, $stroke);
      return;
    }

    list ($stroke_colour, $opacity, $filters) = $this->colourParts($stroke);

    // not a fill colour?
    if($stroke_colour !== 'fill' && $stroke_colour !== 'fillColour') {
      $this->stroke = new Colour($graph, $stroke);
      return;
    }

    $allow_grad_pat = ($stroke_colour === 'fill');

    if($fill !== null) {
      $stroke_colour = new Colour($graph, $fill, $allow_grad_pat, $allow_grad_pat);
    } else {
      $stroke_colour = $graph->getColour($item, $key, $dataset, $allow_grad_pat,
        $allow_grad_pat);
    }

    if($stroke_colour->isNone())
      $stroke_colour = new Colour($graph, 'black');

    $not_solid = $stroke_colour->isGradient() || $stroke_colour->isPattern();

    // if there are no modifications to make, we're done
    if($not_solid || ($opacity >= 1 && $filters == '')) {
      $this->stroke = $stroke_colour;
      return;
    }

    $stroke = $stroke_colour->solid();
    if($opacity < 1)
      $stroke .= ':' . $opacity;
    if($filters != '')
      $stroke .= '/' . $filters;

    $this->stroke = new Colour($graph, $stroke);
  }

  /**
   * Splits a colour into parts
   */
  private static function colourParts($colour)
  {
    $opacity = 1;
    $filters = '';

    if(!empty($colour)) {
      // get opacity / filters
      $spos = strpos($colour, '/');
      if($spos !== false) {
        $filters = substr($colour, $spos + 1);
        $colour = substr($colour, 0, $spos);
      }

      $spos = strpos($colour, ':');
      if($spos !== false) {
        $opacity = substr($colour, $spos + 1);
        $colour = substr($colour, 0, $spos);
      }
    }

    return [$colour, $opacity, $filters];
  }

  public function stroke()
  {
    return $this->stroke;
  }
}

