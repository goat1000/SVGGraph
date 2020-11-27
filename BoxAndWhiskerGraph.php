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

class BoxAndWhiskerGraph extends PointGraph {

  private $min_value = null;
  private $max_value = null;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = [
      'label_centre' => true,
      'require_structured' => ['top', 'bottom', 'wtop', 'wbottom'],
    ];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  protected function draw()
  {
    $body = $this->grid() . $this->underShapes();

    $bar_width = $this->barWidth();
    $dataset = $this->getOption(['dataset', 0], 0);
    $x_axis = $this->x_axes[$this->main_x_axis];

    $bspace = max(0, ($this->x_axes[$this->main_x_axis]->unit() - $bar_width) / 2);
    $whisker_width = $this->getOption('whisker_width');
    $whisker_dash = $this->getOption('whisker_dash');
    $median_width = $this->getOption('median_stroke_width');
    $median_dash = $this->getOption('median_dash');
    $median_colour = $this->getOption('median_colour');
    $bnum = 0;
    $series = '';
    foreach($this->values[$dataset] as $item) {
      $bar_pos = $this->gridPosition($item, $bnum);

      if($item->value !== null && $bar_pos !== null) {

        $box_style = ['fill' => $this->getColour($item, $bnum, $dataset)];
        $this->setStroke($box_style, $item, $bnum, $dataset);
        $this->setLegendEntry($dataset, $bnum, $item, $box_style);

        $top = $item->top;
        $bottom = $item->bottom;
        $round = max($this->getItemOption('bar_round', $dataset, $item), 0);
        $m_colour = null;
        if(!empty($median_colour)) {
          $cg = new ColourGroup($this, $item, $bnum, $dataset, 'median_colour');
          $m_colour = $cg->stroke();
        }
        $shape = $this->whiskerBox($bspace + $bar_pos, $bar_width, $item->value,
          $top, $bottom, $item->wtop, $item->wbottom, $round, $whisker_width,
          $whisker_dash, $median_width, $median_dash, $m_colour);

        // wrap the whisker box in a group
        $g = [];
        $show_label = $this->addDataLabel($dataset, $bnum, $g, $item,
          $bspace + $bar_pos, $this->gridY($top), $bar_width,
          $this->gridY($bottom) - $this->gridY($top));
        if($this->show_tooltips)
          $this->setTooltip($g, $item, $dataset, $item->key, $item->value, $show_label);
        if($this->show_context_menu)
          $this->setContextMenu($g, $dataset, $item, $show_label);

        if($this->semantic_classes)
          $g['class'] = 'series0';
        $group = $this->element('g', array_merge($g, $box_style), null, $shape);
        $series .= $this->getLink($item, $item->key, $group);

        // add outliers as markers
        $x = $bar_pos + $x_axis->unit() / 2;
        foreach($this->getOutliers($item) as $ovalue) {
          $y = $this->gridY($ovalue);
          $this->addMarker($x, $y, $item);
        }
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

    $body .= $series;
    $body .= $this->overShapes();
    $body .= $this->axes();
    $body .= $this->drawMarkers();
    return $body;
  }

  /**
   * Returns the width of a bar
   */
  protected function barWidth()
  {
    if(is_numeric($this->bar_width) && $this->bar_width >= 1)
      return $this->bar_width;
    $unit_w = $this->x_axes[$this->main_x_axis]->unit();
    $bw = $unit_w - $this->bar_space;
    return max(1, $bw, $this->bar_width_min);
  }

  /**
   * Returns the code for a box with whiskers
   */
  protected function whiskerBox($x, $w, $median, $top, $bottom,
    $wtop, $wbottom, $round, $whisker_width, $whisker_dash,
    $median_width, $median_dash, Colour $median_colour = null)
  {
    $t = $this->gridY($top);
    $b = $this->gridY($bottom);
    $wt = $this->gridY($wtop);
    $wb = $this->gridY($wbottom);

    $box = ['x' => $x, 'y' => $t, 'width' => $w, 'height' => $b - $t];
    if($round > 0) {
      $box['rx'] = $box['ry'] = min($round, $box['width'] / 2,
        $box['height'] / 2);
    }
    $rect = $this->element('rect', $box);

    // whisker lines
    $lg = $w * (1 - $whisker_width) * 0.5;
    $ll = $x + $lg;
    $lr = $x + $w - $lg;
    $l = ['x1' => $ll, 'x2' => $lr];
    $l['y1'] = $l['y2'] = $wt;
    $l1 = $this->element('line', $l);
    $l['y1'] = $l['y2'] = $wb;
    $l2 = $this->element('line', $l);

    // median line
    $l['x1'] = $x;
    $l['x2'] = $x + $w;
    $l['y1'] = $l['y2'] = $this->gridY($median);
    $style = [ 'stroke-width' => $median_width ];
    if($median_colour !== null)
      $style['stroke'] = $median_colour;
    if(!empty($median_dash))
      $style['stroke-dasharray'] = $median_dash;
    $l3 = $this->element('line', array_merge($l, $style));

    // whisker dashed lines
    $style = [ 'stroke-dasharray' => $whisker_dash ];
    $l['x1'] = $l['x2'] = $x + $w / 2;
    $l['y1'] = $wt;
    $l['y2'] = $t;
    $w1 = $this->element('line', array_merge($l, $style));
    $l['y1'] = $wb;
    $l['y2'] = $b;
    $w2 = $this->element('line', array_merge($l, $style));

    return $rect . $w1 . $w2 . $l1 . $l2 . $l3;
  }

  /**
   * Checks that the data contains sensible values
   */
  protected function checkValues()
  {
    parent::checkValues();

    foreach($this->values[0] as $item) {
      $val = $item->value;
      if($val === null)
        continue;
      $wb = $item->wbottom;
      $wt = $item->wtop;
      $b = $item->bottom;
      $t = $item->top;
      if($wb > $b || $wt < $t || $val < $b || $val > $t) {

        $wb = new Number($wb);
        $b = new Number($b);
        $wt = new Number($wt);
        $t = new Number($t);
        $val = new Number($val);
        throw new \Exception('Data problem: ' . $wb . '--[' . $b . ' ' . $val .
          ' ' . $t . ']--' . $wt);
      }
    }
  }

  /**
   * Return box for legend
   */
  public function drawLegendEntry($x, $y, $w, $h, $entry)
  {
    $box = ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
    return $this->element('rect', $box, $entry->style);
  }

  /**
   * Returns the maximum bar end
   */
  public function getMaxValue()
  {
    if($this->max_value !== null)
      return $this->max_value;
    $max = null;
    $dataset = $this->getOption(['dataset', 0], 0);
    foreach($this->values[$dataset] as $item) {
      if($item->value === null)
        continue;
      $points = [$item->wtop];
      $points = array_merge($points, $this->getOutliers($item));
      $m = max($points);
      if($max === null || $m > $max)
        $max = $m;
    }
    return ($this->max_value = $max);
  }

  /**
   * Returns the minimum bar end
   */
  public function getMinValue()
  {
    if($this->min_value !== null)
      return $this->min_value;
    $min = null;
    $dataset = $this->getOption(['dataset', 0], 0);
    foreach($this->values[$dataset] as $item) {
      if($item->value === null)
        continue;
      $points = [$item->wbottom];
      $points = array_merge($points, $this->getOutliers($item));
      $m = min($points);
      if($min === null || $m < $min)
        $min = $m;
    }
    return ($this->min_value = $min);
  }

  /**
   * Returns the list of outliers for an item
   */
  protected function getOutliers(&$item)
  {
    $outliers = [];
    if(!isset($this->structure['outliers']) ||
      !is_array($this->structure['outliers']))
      return $outliers;

    $min = $item->wbottom;
    $max = $item->wtop;
    foreach($this->structure['outliers'] as $o) {
      $v = $item->rawData($o);
      if($v !== null && ($v > $max || $v < $min))
        $outliers[] = $v;
    }
    return $outliers;
  }
}

