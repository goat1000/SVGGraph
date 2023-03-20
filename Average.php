<?php
/**
 * Copyright (C) 2020-2023 Graham Breach
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
 * Class for average lines (using guidelines)
 */
class Average {

  private $graph;
  private $lines = [];

  public function __construct(&$graph, &$values, $datasets)
  {
    foreach($datasets as $d) {
      if(!$graph->getOption(['show_average', $d]))
        continue;

      $avg = $this->calculate($values, $d);
      if($avg === null)
        continue;

      $line = [ $avg ];

      $title = $this->getTitle($graph, $avg, $d);
      if($title !== null && strlen($title) > 0)
        $line[] = $title;

      $cg = new ColourGroup($graph, null, 0, $d, 'average_colour');
      $line['colour'] = $cg->stroke();

      $tc = $graph->getOption(['average_title_colour', $d]);
      if(!empty($tc)) {
        $cg = new ColourGroup($graph, null, 0, $d, 'average_title_colour');
        $line['text_colour'] = $cg->stroke();
      }

      $line['stroke_width'] = new Number($graph->getOption(['average_stroke_width', $d], 1));
      $line['font_size'] = Number::units($graph->getOption(['average_font_size', $d]));

      $opts = ["opacity", "above", "dash", "title_align",
        "title_angle", "title_opacity", "title_padding", "title_position",
        "font", "font_adjust", "font_weight", "length", "length_units"];
      foreach($opts as $opt) {
        $g_opt = str_replace('title', 'text', $opt);
        $line[$g_opt] = $graph->getOption(['average_' . $opt, $d]);
      }

      // prevent line from changing graph dimensions
      $line['no_min_max'] = true;
      $this->lines[] = $line;
    }

    $this->graph =& $graph;
  }

  /**
   * Adds the average lines to the graph's guidelines
   */
  public function getGuidelines()
  {
    if(empty($this->lines))
      return;
    $guidelines = Guidelines::normalize($this->graph->getOption('guideline'));
    $this->graph->setOption('guideline', array_merge($guidelines, $this->lines));
  }

  /**
   * Calculates the mean average for a dataset
   */
  protected function calculate(&$values, $dataset)
  {
    $sum = 0;
    $count = 0;
    foreach($values[$dataset] as $p) {
      if($p->value === null || !is_numeric($p->value))
        continue;
      $sum += $p->value;
      ++$count;
    }

    return $count ? $sum / $count : null;
  }

  /**
   * Returns the average line title
   */
  protected function getTitle(&$graph, $avg, $dataset)
  {
    $tcb = $graph->getOption(['average_title_callback', $dataset]);
    if(is_callable($tcb))
      return call_user_func($tcb, $dataset, $avg);

    return $graph->getOption(['average_title', $dataset]);
  }
}
