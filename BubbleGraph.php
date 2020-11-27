<?php
/**
 * Copyright (C) 2013-2020 Graham Breach
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
 * BubbleGraph - scatter graph with bubbles instead of markers
 */
class BubbleGraph extends PointGraph {

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = [
      'repeated_keys' => 'accept',
      'require_integer_keys' => false,
      'require_structured' => ['area'],
    ];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  protected function draw()
  {
    $body = $this->grid() . $this->underShapes();
    $dataset = $this->getOption(['dataset', 0], 0);

    $bnum = 0;
    $y_axis = $this->y_axes[$this->main_y_axis];
    $series = '';

    foreach($this->values[$dataset] as $item) {
      $area = $item->area;
      $x = $this->gridPosition($item, $bnum);
      $y = null;
      if($item->value !== null && $x !== null)
        $y = $this->gridY($item->value);

      if($y !== null) {
        $r = $this->bubble_scale * $y_axis->unit() * sqrt(abs($area) / M_PI);
        $circle = ['cx' => $x, 'cy' => $y, 'r' => $r];
        $colour = $this->getColour($item, $bnum, $dataset);
        $circle_style = ['fill' => $colour];
        if($area < 0) {
          // draw negative bubbles with a checked pattern
          $pattern = [$colour, 'pattern' => 'check', 'size' => 8];
          $pid = $this->defs->addPattern($pattern);
          $circle_style['fill'] = 'url(#' . $pid . ')';
        }
        $this->setStroke($circle_style, $item, $bnum, $dataset);
        $this->addDataLabel($dataset, $bnum, $circle, $item,
          $x - $r, $y - $r, $r * 2, $r * 2);

        if($this->show_tooltips)
          $this->setTooltip($circle, $item, $dataset, $item->key, $area, true);
        if($this->show_context_menu)
          $this->setContextMenu($circle, $dataset, $item, true);
        if($this->semantic_classes)
          $circle['class'] = 'series0';
        $bubble = $this->element('circle', array_merge($circle, $circle_style));
        $series .= $this->getLink($item, $item->key, $bubble);

        $this->addMarker($x, $y, $item, null, $dataset, false);
        $this->setLegendEntry($dataset, $bnum, $item, $circle_style);
      }

      ++$bnum;
    }

    $group = [];
    if($this->semantic_classes)
      $group['class'] = 'series';
    $shadow_id = $this->defs->getShadow();
    if($shadow_id !== null)
      $group['filter'] = 'url(#' . $shadow_id . ')';
    if(!empty($group))
      $series = $this->element('g', $group, null, $series);

    list($best_fit_above, $best_fit_below) = $this->bestFitLines();
    $body .= $best_fit_below;
    $body .= $series;
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

    // prevent drawing actual markers
    $this->marker_size = 0;
  }

  /**
   * Return bubble for legend
   */
  public function drawLegendEntry($x, $y, $w, $h, $entry)
  {
    $bubble = [
      'cx' => $x + $w / 2,
      'cy' => $y + $h / 2,
      'r' => min($w, $h) / 2
    ];
    return $this->element('circle', array_merge($bubble, $entry->style));
  }
}

