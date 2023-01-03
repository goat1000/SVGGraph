<?php
/**
 * Copyright (C) 2009-2022 Graham Breach
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

  protected $line_styles = [];
  protected $fill_styles = [];

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
    if($this->getOption('semantic_classes'))
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
      $figure = $this->getOption('line_figure');
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
      $y_bottom = new Number($fill === 'full' ? $this->height - $this->pad_bottom : $y_bottom);

      $line_points = $this->getLinePoints($points);
      $curve = min(5, max(0, $this->getOption(['line_curve', $dataset])));
      if($curve)
        list($path, $fillpath) = $this->getCurvedLinePath($line_points, $y_bottom, $last_x, $curve);
      else
        list($path, $fillpath) = $this->getLinePath($line_points, $y_bottom, $last_x);

      // close the path?
      if($close)
        $path->add('z');
      $this->line_styles[$dataset] = $attr;
      $attr['d'] = $path;
      if($this->getOption('semantic_classes'))
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
        if($this->getOption('semantic_classes'))
          $fill_style['class'] = 'series' . $dataset;
        $graph_line = $this->element('path', $fill_style) . $graph_line;

        unset($fill_style['d'], $fill_style['class']);
        $this->fill_styles[$dataset] = $fill_style;
      } else {
        $this->fill_styles[$dataset] = null;
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
   * Preprocess the points of a joined line
   */
  protected function getLinePoints($points)
  {
    return $points;
  }

  /**
   * Returns the path for a line
   */
  protected function getLinePath($points, $y_bottom, &$last_x)
  {
    $path = new PathData;
    $fillpath = new PathData;
    $cmd = 'M';

    foreach($points as $point) {
      list($x, $y, $item, $dataset, $index) = $point;
      $x = new Number($x);
      $y = new Number($y);

      if($fillpath->isEmpty()) {
        if($this->getOption('line_figure'))
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
    return [$path, $fillpath];
  }

  /**
   * Returns the path for a curved line
   */
  protected function getCurvedLinePath($points, $y_bottom, &$last_x, $curve)
  {
    $path = new PathData;
    $fillpath = new PathData;

    $t = $curve / 2.0;
    $ctrl = [];
    $last_point = count($points) - 1;
    foreach($points as $i => $point) {
      list($x, $y, $item, $dataset, $index) = $point;
      $nx = new Number($x);
      $ny = new Number($y);

      $p_prev = $points[$i == 0 ? $i + 1 : $i - 1];
      $p_next = $points[$i == $last_point ? $i - 1 : $i + 1];
      $ctrl_prev = $ctrl;
      $ctrl = $this->getControlPoints($p_prev[0], $p_prev[1], $x, $y, $p_next[0], $p_next[1], $t);

      if($i == 0) {
        $path->add('M', $nx, $ny);
        if($this->getOption('line_figure')) {
          $fillpath->add('M', $nx, $ny);
        } else {
          $fillpath->add($this->fillFrom($nx, $y_bottom));
          $fillpath->add('L', $nx, $ny);
        }
      } else {
        $path->add('C', $ctrl_prev[2], $ctrl_prev[3], $ctrl[0], $ctrl[1], $nx, $ny);
        $fillpath->add('C', $ctrl_prev[2], $ctrl_prev[3], $ctrl[0], $ctrl[1], $nx, $ny);
      }

      $last_x = $nx;
    }
    return [$path, $fillpath];
  }

  /**
   * Returns the control points either side of a point
   */
  public static function getControlPoints($x0, $y0, $x1, $y1, $x2, $y2, $t)
  {
    $d1 = sqrt(pow($x1 - $x0, 2) + pow($y1 - $y0, 2));
    $d2 = sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
    $fa = $t * $d1 / ($d1 + $d2);
    $fb = $t - $fa;
    $w = $x2 - $x0;
    $h = $y2 - $y0;
    return [$x1 - $fa * $w, $y1 - $fa * $h, $x1 + $fb * $w, $y1 + $fb * $h];
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
    $style_info['line_style'] = isset($this->line_styles[$dataset]) ?
      $this->line_styles[$dataset] : null;
    $style_info['fill_style'] = isset($this->fill_styles[$dataset]) ?
      $this->fill_styles[$dataset] : null;
    parent::setLegendEntry($dataset, $index, $item, $style_info);
  }

  /**
   * Return line and marker for legend
   */
  public function drawLegendEntry($x, $y, $w, $h, $entry)
  {
    $marker = parent::drawLegendEntry($x, $y, $w, $h, $entry);
    $graph_line = '';

    $h1 = $h/2;
    $y += $h1;

    if(isset($entry->style['line_style'])) {
      $line = $entry->style['line_style'];
      $line['d'] = new PathData('M', $x, $y, 'l', $w, 0);
      $graph_line = $this->element('path', $line);
    }
    if(isset($entry->style['fill_style'])) {
      $fill = $entry->style['fill_style'];
      $fill['d'] = new PathData('M', $x, $y, 'l', $w, 0, 0, $h1, -$w, 0, 'z');
      $graph_line = $this->element('path', $fill) . $graph_line;
    }
    return $graph_line . $marker;
  }
}

