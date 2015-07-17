<?php
/**
 * Copyright (C) 2009-2015 Graham Breach
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

require_once 'SVGGraphGridGraph.php';

class BarGraph extends GridGraph {

  protected $bar_styles = array();
  protected $label_centre = TRUE;

  public function __construct($w, $h, $settings = NULL)
  {
    // backwards compatibility
    if(isset($settings['show_bar_labels']))
      $settings['show_data_labels'] = $settings['show_bar_labels'];

    parent::__construct($w, $h, $settings);
  }

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);
    $bnum = 0;
    $bar_width = $this->BarWidth();
    $bspace = $this->BarSpace($bar_width);
    $this->ColourSetup($this->values->ItemsCount());
    $bars = '';

    foreach($this->values[0] as $item) {

      // assign bar in the loop so it doesn't keep ID
      $bar = array('width' => $bar_width);
      if($this->semantic_classes)
        $bar['class'] = 'series0';
      $bar_pos = $this->GridPosition($item->key, $bnum);
      if($this->legend_show_empty || $item->value != 0) {
        $bar_style = array('fill' => $this->GetColour($item, $bnum));
        $this->SetStroke($bar_style, $item);
      } else {
        $bar_style = NULL;
      }

      if(!is_null($item->value) && !is_null($bar_pos)) {
        $bar['x'] = $bspace + $bar_pos;
        $this->Bar($item->value, $bar);

        if($bar['height'] > 0) {
          $show_label = $this->AddDataLabel(0, $bnum, $bar, $item, $bar['x'],
            $bar['y'], $bar['width'], $bar['height']);
          if($this->show_tooltips)
            $this->SetTooltip($bar, $item, 0, $item->key, $item->value,
              !$this->compat_events && $show_label);
          $rect = $this->Element('rect', $bar, $bar_style);
          $bars .= $this->GetLink($item, $item->key, $rect);
        }
      }
      $this->bar_styles[] = $bar_style;
      ++$bnum;
    }

    if($this->semantic_classes)
      $bars = $this->Element('g', array('class' => 'series'), NULL, $bars);
    $body .= $bars;
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
    return $body;
  }

  /**
   * Returns the width of a bar
   */
  protected function BarWidth()
  {
    if(is_numeric($this->bar_width) && $this->bar_width >= 1)
      return $this->bar_width;
    $unit_w = $this->x_axes[$this->main_x_axis]->Unit();
    return $this->bar_space >= $unit_w ? '1' : $unit_w - $this->bar_space;
  }

  /**
   * Returns the space before a bar
   */
  protected function BarSpace($bar_width)
  {
    return max(0, ($this->x_axes[$this->main_x_axis]->Unit() - $bar_width) / 2);
  }

  /**
   * Fills in the y-position and height of a bar
   * @param number $value bar value
   * @param array  &$bar  bar element array [out]
   * @param number $start bar start value
   * @param number $axis bar Y-axis number
   * @return number unclamped bar position
   */
  protected function Bar($value, &$bar, $start = null, $axis = NULL)
  {
    if($start)
      $value += $start;

    $startpos = is_null($start) ? $this->OriginY($axis) :
      $this->GridY($start, $axis);
    if(is_null($startpos))
      $startpos = $this->OriginY($axis);
    $pos = $this->GridY($value, $axis);
    if(is_null($pos)) {
      $bar['height'] = 0;
    } else {
      $l1 = $this->ClampVertical($startpos);
      $l2 = $this->ClampVertical($pos);
      $bar['y'] = min($l1, $l2);
      $bar['height'] = abs($l1-$l2);
    }
    return $pos;
  }

  /**
   * Override to check minimum space requirement
   */
  protected function AddDataLabel($dataset, $index, &$element, &$item,
    $x, $y, $w, $h, $content = NULL, $duplicate = TRUE)
  {
    if($h < $this->ArrayOption($this->data_label_min_space, $dataset))
      return false;
    return parent::AddDataLabel($dataset, $index, $element, $item, $x, $y,
      $w, $h, $content, $duplicate);
  }

  /**
   * Returns the position for a data label
   */
  public function DataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    $pos = parent::DataLabelPosition($dataset, $index, $item, $x, $y, $w, $h,
      $label_w, $label_h);
    $bpos = $this->bar_label_position;
    if(!empty($bpos))
      $pos = $bpos;

    if($label_h > $h && Graph::IsPositionInside($pos))
      $pos = str_replace(array('top','middle','bottom'), 'outside top inside ', $pos);

    // flip top/bottom for negative values
    if($item->value < 0) {
      if(strpos($pos, 'top') !== FALSE)
        $pos = str_replace('top','bottom', $pos);
      elseif(strpos($pos, 'above') !== FALSE)
        $pos = str_replace('above','below', $pos);
      elseif(strpos($pos, 'below') !== FALSE)
        $pos = str_replace('below','above', $pos);
      elseif(strpos($pos, 'bottom') !== FALSE)
        $pos = str_replace('bottom','top', $pos);
    }

    return $pos;
  }

  /**
   * Returns the style options for bar labels
   */
  public function DataLabelStyle($dataset, $index, &$item)
  {
    $style = parent::DataLabelStyle($dataset, $index, $item);

    // bar label settings can override global settings
    $opts = array(
      'font' => 'bar_label_font',
      'font_size' => 'bar_label_font_size',
      'font_weight' => 'bar_label_font_weight',
      'colour' => 'bar_label_colour',
      'altcolour' => 'bar_label_colour_above',
      'space' => 'bar_label_space',
    );
    foreach($opts as $key => $opt)
      if(isset($this->settings[$opt]))
        $style[$key] = $this->settings[$opt];
    return $style;
  }

  /**
   * Return box for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!isset($this->bar_styles[$set]))
      return '';

    $bar = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h);
    return $this->Element('rect', $bar, $this->bar_styles[$set]);
  }

}

