<?php
/**
 * Copyright (C) 2020 Graham Breach
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
 * Class for drawing best-fit lines
 */
class BestFit {

  protected $graph;
  protected $bbox;
  protected $lines_below = [];
  protected $lines_above = [];

  public function __construct(Graph &$graph, BoundingBox $bbox)
  {
    $this->graph =& $graph;
    $this->bbox = $bbox;
  }

  /**
   * Adds a line
   */
  public function add($dataset, $points)
  {
    $type = $this->graph->getOption(['best_fit', $dataset]);
    if($type !== 'straight')
      return;

    // range and projection
    $r_start = $r_end = $p_start = $p_end = null;
    list($start, $end) = $this->getRange($dataset);

    if($start !== null || $end !== null) {
      if($start !== null)
        $r_start = $this->graph->unitsX($start);
      if($end !== null)
        $r_end = $this->graph->unitsX($end);
      $project = $this->graph->getOption(['best_fit_project', $dataset]);
      $p_start = $project == 'start' || $project == 'both';
      $p_end = $project == 'end' || $project == 'both';
      $project = $p_start || $p_end;

      $points = $this->filterPoints($points, $r_start, $r_end);
    }

    $best_fit = new BestFitLine($this->graph, $points);
    $best_fit->calculate($this->bbox, $r_start, $r_end, $p_start, $p_end);
    if($this->graph->getOption(['best_fit_above', $dataset]))
      $this->lines_above[$dataset] = $best_fit;
    else
      $this->lines_below[$dataset] = $best_fit;
  }

  /**
   * Returns the start and end of selection range
   */
  protected function getRange($dataset)
  {
    $range = $this->graph->getOption(['best_fit_range', $dataset]);
    if(!is_array($range))
      $range = $this->graph->getOption('best_fit_range');
    if(!is_array($range))
      return [null, null];
    if(count($range) !== 2)
      throw new \Exception('Best fit range must contain start and end values');
    if($range[0] !== null && !is_numeric($range[0]))
      throw new \Exception('Best fit range start not numeric or NULL');
    if($range[1] !== null && !is_numeric($range[1]))
      throw new \Exception('Best fit range end not numeric or NULL');
    if($range[0] !== null && $range[1] !== null && $range[1] <= $range[0])
      throw new \Exception('Best fit range start >= end');
    return $range;
  }

  /**
   * Filters out points outside the range
   */
  protected function filterPoints($points, $start, $end)
  {
    if($start === null && $end === null)
      return $points;

    if($start === null)
      $callback = function($p) use ($end) { return $p->x <= $end; };
    elseif($end === null)
      $callback = function($p) use ($start) { return $p->x >= $start; };
    else
      $callback = function($p) use ($start, $end) { return $p->x <= $end && $p->x >= $start; };

    return array_filter($points, $callback);
  }

  /**
   * Returns the lines that go above the graph
   */
  public function getAbove()
  {
    return $this->getLines('lines_above');
  }

  /**
   * Returns the lines below the graph
   */
  public function getBelow()
  {
    return $this->getLines('lines_below');
  }

  /**
   * Creates the markup for a group of lines
   */
  private function getLines($which_lines)
  {
    $lines = '';
    foreach($this->{$which_lines} as $dataset => $best_fit) {
      $line_path = $best_fit->getLine();
      if($line_path->isEmpty())
        continue;

      $proj_path = $best_fit->getProjection();
      $lines .= $this->getLinePath($dataset, $line_path, $proj_path);
    }
    if($lines == '')
      return $lines;

    if($this->graph->getOption('semantic_classes')) {
      $cls = ['class' => 'bestfit'];
      $lines = $this->graph->element('g', $cls, null, $lines);
    }
    return $lines;
  }

  /**
   * Wraps up the PathData with SVG
   */
  protected function getLinePath($dataset, $line_path, $proj_path)
  {
    // use ColourGroup to support fill and fillColour
    $cg = new ColourGroup($this->graph, null, 0, $dataset, 'best_fit_colour');
    $colour = $cg->stroke();
    $stroke_width = $this->graph->getOption(['best_fit_width', $dataset]);
    $dash = $this->graph->getOption(['best_fit_dash', $dataset]);
    $opacity = $this->graph->getOption(['best_fit_opacity', $dataset]);
    $above = $this->graph->getOption(['best_fit_above', $dataset]);
    $path = [
      'd' => $line_path,
      'stroke' => $colour->isNone() ? '#000' : $colour,
    ];
    if($stroke_width != 1 && $stroke_width > 0)
      $path['stroke-width'] = $stroke_width;
    if(!empty($dash))
      $path['stroke-dasharray'] = $dash;
    if($opacity != 1)
      $path['opacity'] = $opacity;

    $line = $this->graph->element('path', $path);
    if($proj_path->isEmpty())
      return $line;

    // append the projection path
    $path['d'] = $proj_path;
    $cg = new ColourGroup($this->graph, null, 0, $dataset, 'best_fit_project_colour');
    $colour = $cg->stroke();
    $stroke_width = $this->graph->getOption(['best_fit_project_width', $dataset]);
    $dash = $this->graph->getOption(['best_fit_project_dash', $dataset]);
    $opacity = $this->graph->getOption(['best_fit_project_opacity', $dataset]);

    if(!$colour->isNone())
      $path['stroke'] = $colour;
    if($stroke_width > 0)
      $path['stroke-width'] = $stroke_width;
    if(!empty($dash))
      $path['stroke-dasharray'] = $dash;
    if($opacity > 0)
      $path['opacity'] = $opacity;

    $line .= $this->graph->element('path', $path);
    return $line;
  }
}

