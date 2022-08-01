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
 * An axis with multiple levels of divisions
 */
class DisplayAxisLevels extends DisplayAxis {

  protected $level_count;
  protected $levels;

  /**
   * Pass all these to parent constructor
   */
  public function __construct(&$graph, &$axis, $axis_no, $orientation, $type,
    $main, $label_centre)
  {
    parent::__construct($graph, $axis, $axis_no, $orientation, $type, $main,
      $label_centre);

    if(!$this->show_text)
    {
      $this->levels = [null];
      $this->level_count = 1;
      return;
    }

    $levels = $graph->getOption(['axis_levels_' . $orientation, $axis_no]);
    if(!is_array($levels))
      $levels = array_fill(0, $levels, null);
    $this->levels = $levels;
    $this->level_count = count($levels);
  }

  /**
   * Returns the extents of the axis, relative to where it will be drawn from
   *  returns BoundingBox
   */
  public function measure($with_label = true)
  {
    if($this->level_count <= 1)
      return parent::measure($with_label);

    $bbox = new BoundingBox(0, 0, 0, 0);
    for($i = 0; $i < $this->level_count; ++$i) {
      $dbox = $this->getDivisionsBBox($i);
      $tbox = $this->getTextBBox($i);
      if($this->orientation == 'h') {
        $dbox->flipY();
        if($this->axis_no > 0) {
          $dbox->offset(0, $bbox->y1);
          $tbox->offset(0, $bbox->y1);
        } else {
          $dbox->offset(0, $bbox->y2);
          $tbox->offset(0, $bbox->y2);
        }
      } else {
        if($this->axis_no > 0) {
          $dbox->offset($bbox->x2, 0);
          $tbox->offset($bbox->x2, 0);
        } else {
          $dbox->offset($bbox->x1, 0);
          $tbox->offset($bbox->x1, 0);
        }
      }
      // store the text box for this level
      $this->levels[$i]['text_box'] = $tbox;

      $bbox->growBox($tbox);
      $bbox->growBox($dbox);
    }

    if($with_label && $this->show_label) {
      $lpos = $this->getLabelPosition();
      $bbox->grow($lpos['x'], $lpos['y'], $lpos['x'] + $lpos['width'],
        $lpos['y'] + $lpos['height']);
    }
    $bbox = $this->addOffset($bbox);

    return $bbox;
  }

  /**
   * Returns the bounding box of the text
   */
  protected function getTextBBox($level)
  {
    if($this->level_count <= 1 || !$this->boxed_text)
      return parent::getTextBBox($level);

    $bbox = new BoundingBox(0, 0, 0, 0);
    list($x_off, $y_off, $opp) = $this->getTextOffset(0, 0, 0, 0, 0, 0, $level);
    $t_offset = ($this->orientation == 'h' ? $x_off : $y_off);
    if($this->axis->reversed())
      $t_offset = -$t_offset;
    $length = $this->axis->getLength();

    $points = $this->axis->getGridPoints(0);
    $lpoints = $this->getPointsForLevel($points, $level);
    $positions = $this->getTextPositions(0, 0, $x_off, $y_off, $lpoints, null);
    $pcount = count($positions);
    for($p = 0; $p < $pcount; ++$p) {
      $point = $lpoints[$p];
      if(!$this->pointTextVisible($point, $length, $t_offset))
        continue;
      if(!$point->blank($level)) {
        $pos = $positions[$p];
        $lbl = $this->measureText($pos['x'], $pos['y'], $point, $opp, $level);
        $bbox->grow($lbl['x'], $lbl['y'], $lbl['x'] + $lbl['width'],
          $lbl['y'] + $lbl['height']);
      }
    }
    return $bbox;
  }

  /**
   * Draws the axis text labels
   */
  public function drawText($x, $y, $gx, $gy, $g_width, $g_height)
  {
    if($this->level_count <= 1)
      return parent::drawText($x, $y, $gx, $gy, $g_width, $g_height);

    list($x_offset, $y_offset, $opposite) = $this->getTextOffset($x, $y,
      $gx, $gy, $g_width, $g_height, 0);

    $t_offset = ($this->orientation == 'h' ? $x_offset : $y_offset);
    if($this->axis->reversed())
      $t_offset = -$t_offset;

    // measure function fills in text locations
    $this->measure();
    $labels = '';
    $anchor = null;
    $points = $this->axis->getGridPoints(0);
    $length = $this->axis->getLength();

    for($i = 0; $i < $this->level_count; ++$i) {
      $count = count($points);
      if($this->block_label)
        --$count;

      list($x_offset, $y_offset, $opposite) = $this->getTextOffset($x, $y,
        $gx, $gy, $g_width, $g_height, $i);
      $x1 = $x_offset;
      $y1 = $y_offset;

      $tbox = $this->levels[$i]['text_box'];
      if($this->orientation == 'h') {
        if($this->axis_no > 0)
          $y1 = $y_offset + $tbox->y2;
        else
          $y1 = $y_offset + $tbox->y1;
        switch($this->styles['t_align']) {
        case 'left':
          $anchor = 'start';
          break;
        case 'right':
          $anchor = 'end';
          break;
        }
      } else {
        if($this->axis_no > 0)
          $x1 = $x_offset + $tbox->x1;
        else
          $x1 = $x_offset + $tbox->x2;
        switch($this->styles['t_align']) {
        case 'left':
          if(!$opposite) {
            $anchor = 'start';
            $x1 = $tbox->x1;
          }
          break;
        case 'centre':
          $x1 = ($tbox->x1 + $tbox->x2) / 2;
          $anchor = 'middle';
          break;
        }
      }

      $lpoints = $this->getPointsForLevel($points, $i);
      $positions = $this->getTextPositions($x, $y, $x1, $y1, $lpoints, $anchor);
      $pcount = count($positions);
      for($p = 0; $p < $pcount; ++$p) {
        $point = $lpoints[$p];
        if(!$this->pointTextVisible($point, $length, $t_offset))
          continue;
        $pos = $positions[$p];
        $labels .= $this->getText($pos['x'], $pos['y'], $point, $opposite, $i, $anchor);
      }
    }
    if($labels != '') {
      $group = [
        'font-family' => $this->styles['t_font'],
        'font-size' => $this->styles['t_font_size'],
        'fill' => $this->styles['t_colour'],
      ];
      $weight = $this->styles['t_font_weight'];
      if($weight != 'normal' && $weight !== null)
        $group['font-weight'] = $weight;

      $labels = $this->graph->element('g', $group, null, $labels);
    }

    if($this->show_label)
      $labels .= $this->getLabel($x, $y, $gx, $gy, $g_width, $g_height);

    return $labels;
  }

  /**
   * Draws the axis divisions
   */
  public function drawDivisions($x, $y, $g_width, $g_height)
  {
    // parent can do simple divisions
    if($this->level_count <= 1 || !$this->boxed_text)
      return parent::drawDivisions($x, $y, $g_width, $g_height);

    $path = '';
    $points = $this->axis->getGridPoints(0);
    $x_offset = $y_offset = 0;

    // direction of offset depends on h/v and axis number
    $direction = ($this->orientation == 'v' ? -1 : 1);
    if($this->axis_no > 0)
      $direction = -$direction;

    for($i = 0; $i < $this->level_count; ++$i) {
      $path_info = $this->getDivisionPathInfo(false, $g_width, $g_height, $i);
      if($path_info === null)
        continue;

      $lpoints = $this->getPointsForLevel($points, $i);
      $path .= $this->getDivisionPath($x + $x_offset, $y + $y_offset, $lpoints,
        $path_info, $i);

      if($this->orientation == 'h')
        $y_offset += $direction * $path_info['sz'];
      else
        $x_offset += $direction * $path_info['sz'];
    }
    if($path == '')
      return '';

    $attr = [
      'd' => $path,
      'stroke' => $this->styles['d_colour'],
      'fill' => 'none',
    ];
    return $this->graph->element('path', $attr);
  }

  /**
   * Returns the list of points with text for $level
   */
  protected function getPointsForLevel($points, $level)
  {
    // need first point even if it is blank
    $lpoints = [$points[0]];
    $prev = $points[0]->getText($level);

    $last_point = count($points) - 1;
    $end = $this->boxed_text ? $last_point : $last_point + 1;
    for($p = 1; $p < $end; ++$p) {
      if($points[$p]->blank($level))
        continue;

      $label = $points[$p]->getText($level);
      if($label == $prev)
        continue;

      $lpoints[] = $points[$p];
      $prev = $label;
    }

    // the last point is the division at the end of the axis
    if($this->boxed_text)
      $lpoints[] = $points[$last_point];
    return $lpoints;
  }
}

