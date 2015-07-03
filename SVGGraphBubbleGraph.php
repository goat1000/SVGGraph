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

require_once 'SVGGraphPointGraph.php';

/**
 * BubbleGraph - scatter graph with bubbles instead of markers
 */
class BubbleGraph extends PointGraph {

  protected $repeated_keys = 'accept';
  protected $require_structured = array('area');
  protected $require_integer_keys = false;
  protected $bubble_styles = array();

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);
    $this->ColourSetup($this->values->ItemsCount());

    $bnum = 0;
    $y_axis = $this->y_axes[$this->main_y_axis];
    $series = '';
    foreach($this->values[0] as $item) {
      $area = $item->Data('area');
      $point_pos = $this->GridPosition($item->key, $bnum);
      if(!is_null($item->value) && !is_null($point_pos)) {
        $x = $point_pos;
        $y = $this->GridY($item->value);
        if(!is_null($y)) {
          $r = $this->bubble_scale * $y_axis->Unit() * sqrt(abs($area) / M_PI);
          $circle = array('cx' => $x, 'cy' => $y, 'r' => $r);
          if($area < 0) {
            // draw negative bubbles with a checked pattern
            $pattern = array(
              $this->GetColour($item, $bnum),
              'pattern' => 'check', 'size' => 8
            );
            $pid = $this->AddPattern($pattern);
            $circle_style = array('fill' => "url(#{$pid})");
          } else {
            $circle_style = array('fill' => $this->GetColour($item, $bnum));
          }
          $this->SetStroke($circle_style, $item);
          $show_label = $this->AddDataLabel(0, $bnum, $circle, $item,
            $x - $r, $y - $r, $r * 2, $r * 2);

          if($this->show_tooltips)
            $this->SetTooltip($circle, $item, 0, $item->key, $area,
              !$this->compat_events);
          if($this->semantic_classes)
            $circle['class'] = "series0";
          $bubble = $this->Element('circle', array_merge($circle, $circle_style));
          $series .= $this->GetLink($item, $item->key, $bubble);

          $this->bubble_styles[] = $circle_style;
        }
      }
      ++$bnum;
    }

    if($this->semantic_classes)
      $series = $this->Element('g', array('class' => 'series'), NULL, $series);
    $body .= $series . $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
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

  /**
   * Return bubble for legend
   */
  public function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!array_key_exists($set, $this->bubble_styles))
      return '';

    $bubble = array(
      'cx' => $x + $w / 2,
      'cy' => $y + $h / 2,
      'r' => min($w, $h) / 2
    );
    return $this->Element('circle', array_merge($bubble, $this->bubble_styles[$set]));
  }
}

