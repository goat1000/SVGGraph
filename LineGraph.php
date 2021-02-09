<?php
/**
 * Copyright (C) 2009-2020 Graham Breach
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
 * LineGraph - joined line, with axes and grid
 */
class LineGraph extends PointGraph {

  protected $curr_line_style = null;
  protected $curr_fill_style = null;

  /**
   * Override constructor to handle some odd settings
   */
  public function __construct($width, $height, array $settings, array $fixed_settings = [])
  {
    $fs = ['require_integer_keys' => false];
    if(isset($settings['line_figure']) && $settings['line_figure']) {

      // drawing figures means keeping data in order and accepting
      // repeated keys
      $fs['sort_keys'] = false;
      $fs['repeated_keys'] = 'accept';
    }
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($width, $height, $settings, $fs);
  }

  protected function draw()
  {
    $body = $this->grid() . $this->underShapes();
    $dataset = $this->getOption(['dataset', 0], 0);

    $bnum = 0;
    $cmd = 'M';
    $y_axis_pos = $this->height - $this->pad_bottom -
      $this->y_axes[$this->main_y_axis]->zero();
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);

    $graph_line = '';
    $line_breaks = $this->getOption(['line_breaks', $dataset]);
    $points = [];
    foreach($this->values[$dataset] as $item) {
      if($line_breaks && $item->value === null && count($points) > 0) {
        $graph_line .= $this->drawLine($dataset, $points, $y_bottom);
        $points = [];
      } else {
        $x = $this->gridPosition($item, $bnum);
        if($item->value !== null && $x !== null) {
          $y = $this->gridY($item->value);
          $points[] = [$x, $y, $item, $dataset, $bnum];
        }
      }
      ++$bnum;
    }

    $graph_line .= $this->drawLine($dataset, $points, $y_bottom);
    $group = [];
    $this->clipGrid($group);
    if($this->semantic_classes)
      $group['class'] = 'series';
    if(!empty($group))
      $graph_line = $this->element('g', $group, null, $graph_line);

    $group = [];
    $shadow_id = $this->defs->getShadow();
    if($shadow_id !== null)
      $group['filter'] = 'url(#' . $shadow_id . ')';
    if(!empty($group))
      $graph_line = $this->element('g', $group, null, $graph_line);

    list($best_fit_above, $best_fit_below) = $this->bestFitLines();
    $body .= $best_fit_below;
    $body .= $graph_line;
    $body .= $this->overShapes();
    $body .= $this->axes();
    $body .= $this->drawMarkers();
    $body .= $best_fit_above;
    return $body;
  }

  /**
   * Line graphs and lines in general require at least two points
   */
  protected function checkValues()
  {
    parent::checkValues();

    if($this->values->itemsCount() <= 1)
      throw new \Exception('Not enough values for ' . get_class($this));
  }

  /**
   * Returns the SVG fragemnt for a line
   * $points = array of array($x, $y, $item, $dataset, $index)
   *   use NULL $item for non-data points
   */
  public function drawLine($dataset, $points, $y_bottom)
  {
    $graph_line = '';

    // can't draw a line between fewer than 2 points
    if(count($points) > 1) {
      $figure = $this->line_figure;
      $close = $figure && $this->getOption(['line_figure_closed', $dataset]);
      $fill = $this->getOption(['fill_under', $dataset]);
      $dash = $this->getOption(['line_dash', $dataset]);
      $stroke_width = $this->getOption(['line_stroke_width', $dataset]);
      $attr = ['fill' => 'none'];

      $cg = new ColourGroup($this, null, 0, $dataset);
      $attr['stroke'] = $cg->stroke();

      if(!empty($dash))
        $attr['stroke-dasharray'] = $dash;
      $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;
      $path = new PathData;
      $fillpath = new PathData;
      $cmd = 'M';
      $y_bottom = new Number($y_bottom);
      foreach($points as $point) {
        list($x, $y, $item, $dataset, $index) = $point;
        $x = new Number($x);
        $y = new Number($y);

        if($fillpath->isEmpty()) {
          if($figure)
            $fillpath->add('M', $x, $y);
          else
            $fillpath->add($this->fillFrom($x, $y_bottom));
          $fillpath->add('L');
        }

        $path->add($cmd, $x, $y);
        $fillpath->add($x, $y);

        // no need to repeat same L command
        $cmd = $cmd == 'M' ? 'L' : '';
        $last_x = $x;
      }

      // close the path?
      if($close)
        $path->add('z');
      $this->curr_line_style = $attr;
      $attr['d'] = $path;
      if($this->semantic_classes)
        $attr['class'] = 'series' . $dataset;
      $graph_line = $this->element('path', $attr);

      if($fill) {
        $opacity = $this->getOption(['fill_opacity', $dataset]);
        if(!$figure)
          $fillpath->add($this->fillTo($last_x, $y_bottom));
        $fill_style = [
          'fill' => $this->getColour(null, 0, $dataset),
          'd' => $fillpath,
          'stroke' => 'none',
        ];
        if($opacity < 1)
          $fill_style['opacity'] = $opacity;
        if($this->semantic_classes)
          $fill_style['class'] = 'series' . $dataset;
        $graph_line = $this->element('path', $fill_style) . $graph_line;

        unset($fill_style['d'], $fill_style['class']);
        $this->curr_fill_style = $fill_style;
      } else {
        $this->curr_fill_style = null;
      }
    }

    // add markers (and therefore legend entries too)
    foreach($points as $point) {
      list($x, $y, $item, $dataset, $index) = $point;

      if($item !== null) {
        $marker_id = $this->markerLabel($dataset, $index, $item, $x, $y);
        $extra = empty($marker_id) ? null : ['id' => $marker_id];
        $this->addMarker($x, $y, $item, $extra, $dataset);
      }
    }
    return $graph_line;
  }

  /**
   * Returns the path segment to start filling under line
   */
  protected function fillFrom(Number $x, Number $y_axis)
  {
    return new PathData('M', $x, $y_axis);
  }

  /**
   * Returns the path segment to end filling under line
   */
  protected function fillTo(Number $x, Number $y_axis)
  {
    return new PathData('L', $x, $y_axis, 'z');
  }

  /**
   * Override to add the line info and marker at the same time
   */
  protected function setLegendEntry($dataset, $index, $item, $style_info)
  {
    $style_info['line_style'] = $this->curr_line_style;
    $style_info['fill_style'] = $this->curr_fill_style;
    parent::setLegendEntry($dataset, $index, $item, $style_info);
  }

  /**
   * Return line and marker for legend
   */
  public function drawLegendEntry($x, $y, $w, $h, $entry)
  {
    if(!isset($entry->style['line_style'], $entry->style['fill_style'])) {
      // No legend entry if no line or fill style is specified.
      return '';
    }
    $marker = parent::drawLegendEntry($x, $y, $w, $h, $entry);
    $graph_line = '';

    if(isset($entry->style['line_style'])) {
      $h1 = $h/2;
      $y += $h1;
      $line = $entry->style['line_style'];
      $line['d'] = new PathData('M', $x, $y, 'l', $w, 0);
      $graph_line = $this->element('path', $line);
    }
    if($entry->style['fill_style'] !== null) {
      $fill = $entry->style['fill_style'];
      $fill['d'] = new PathData('M', $x, $y, 'l', $w, 0, 0, $h1, -$w, 0, 'z');
      $graph_line = $this->element('path', $fill) . $graph_line;
    }
    return $graph_line . $marker;
  }
}

