<?php
/**
 * Copyright (C) 2022 Graham Breach
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

class GanttChart extends HorizontalBarGraph {

  protected $start_date = null;
  protected $end_date = null;
  protected $auto_format = true;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    // if the format for the date/time axis is not set, figure one out
    $this->auto_format = !isset($settings['datetime_text_format']);

    $fs = ['require_structured' => ['end'], ];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  /**
   * Converts dates early
   */
  public function values($values)
  {
    $res = parent::values($values);
    if(empty($values) || $this->values->error)
      return $res;

    // convert times to seconds, find start and end
    $start_date = $end_date = null;
    $update_times = function($item) use (&$start_date, &$end_date) {
      if(!isset($item->value))
        return;
      $s = Graph::dateConvert($item->value);
      if($s !== null) {
        if($start_date === null || $start_date > $s)
          $start_date = $s;
        $item->value = $s;

        $e = isset($item->end) ? Graph::dateConvert($item->end) : null;
        if($e === null) {
          $e = $s;
        } else {
          $e = new \DateTime('@' . $e);
          $e->setTime(23, 59, 50); // just in case of leap seconds
          $e = $e->format('U');
        }

        if($end_date === null || $end_date < $e)
          $end_date = $e;
        $item->end = $e;
      }
      return $item;
    };
    $this->values->transform($update_times);

    // find groups
    $groups = [];
    $item_groups = [];
    $key = null;
    $entries = 0;
    $levels = [];
    foreach($this->values[0] as $item) {

      if($item->group !== null) {

        // numeric group has max number of entries
        $entries = is_numeric($item->group) ? (int)$item->group : 1e6;
        $key = $item->key;
        $groups[$key] = [
          'start' => 0,
          'end' => 0,
          'total_time' => 0,
          'total_complete' => 0,
          'level' => 0,
        ];

        // named groups for multiple levels
        $group_name = is_string($item->group) ? $item->group : 'unnamed_group';
        if(isset($levels[$group_name])) {
          $new_levels = [];
          foreach($levels as $k => $v) {
            if($k == $group_name)
              break;
            $new_levels[$k] = $v;
          }
          $levels = $new_levels;
        }

        $levels[$group_name] = $key;
        $groups[$key]['level'] = count($levels);
        continue;
      }

      // not a group
      if($key !== null && $entries) {
        $item_groups[$item->key] = $key;
        $item_time = $item->end - $item->value;
        $item_percent = $item_time > 0 ? $item_time * $item->complete / 100 : 0;

        // update group hierarchy
        foreach($levels as $level => $key) {
          $g = &$groups[$key];
          if($g['start'] == 0 || $item->value < $g['start'])
            $g['start'] = $item->value;
          if($g['end'] == 0 || $item->value > $g['end'])
            $g['end'] = $item->value;
          if($g['end'] == 0 || $item->end > $g['end'])
            $g['end'] = $item->end;
          if($item_time > 0) {
            $g['total_time'] += $item_time;
            $g['total_complete'] += $item_percent;
          }
        }
        --$entries;
      }
    }

    // update groups with real dates, percentages, text classes
    $this->values->addField('axis_text_class');

    $fix_groups = function($item) use ($groups, $item_groups) {
      if(!isset($groups[$item->key])) {
        if(isset($item->axis_text_class))
          return null;

        $level = 0;
        if(isset($item_groups[$item->key]))
          $level = $groups[$item_groups[$item->key]]['level'];
        $item->axis_text_class = isset($item->milestone) ?
          'gantt_milestone:' . $level :
          'gantt_item:' . $level;
        return $item;
      }

      $g =& $groups[$item->key];
      $item->value = $g['start'];
      $item->end = $g['end'];
      if($g['total_time'] && $g['total_complete']) {
        $item->complete = 100 * $g['total_complete'] / $g['total_time'];
      }
      $item->axis_text_class = 'gantt_group:' . $g['level'];
      return $item;
    };
    $this->values->transform($fix_groups);

    // copy found dates to class
    $this->start_date = $start_date;
    $this->end_date = $end_date;
  }

  /**
   * Sets up the colours used for the graph, and other things
   */
  protected function setup()
  {
    $dataset = $this->getOption(['dataset', 0], 0);
    $icount = $this->values->itemsCount($dataset);

    // axis min/max alter number of items
    $max = $this->getOption(['axis_max_v', 0], 1e7) + 1;
    if($max < $icount)
      $icount = $max;
    $min = $this->getOption(['axis_min_v', 0], 0);
    if($min > 0)
      $icount -= $min;
    if($icount < 1)
      throw new \Exception('No items to display');

    // use two datasets for main/completed colour
    $this->colourSetup($icount, 2);
    if(!is_numeric($this->height))
      $this->autoHeight($icount);
  }

  /**
   * Setup code here for before drawing starts but after axes set
   */
  protected function barSetup()
  {
    parent::barSetup();

    $shapes = $this->getDayShading();
    if($this->getOption('gantt_today')) {
      $ds = $this->getToday();
      if(!empty($ds))
        $shapes = array_merge($shapes, $ds);
    }

    if(!empty($shapes)) {
      $o_shapes = $this->getOption('shape');
      if(is_array($o_shapes)) {
        if(is_array($o_shapes[0]))
          $shapes = array_merge($shapes, $o_shapes);
        else
          $shapes[] = $o_shapes;
      }
      $this->setOption('shape', $shapes);
    }
  }

  /**
   * Calculates a height for the graph
   */
  private function autoHeight($items)
  {
    $axis = null;
    $v_d_a = new DisplayAxis($this, $axis, 0, 'v', 'x', true, false);
    $h_d_a = new DisplayAxis($this, $axis, 0, 'h', 'x', true, false);
    $v_style = $v_d_a->getStyling();
    $h_style = $h_d_a->getStyling();

    // fixed space allows for two lines of labels, plus two of headings
    $fixed = $this->pad_bottom + $this->pad_top;
    $space = isset($h_style['t_space']) ? $h_style['t_space'] : 1;
    if(isset($h_style['t_font_size']))
      $fixed += ($space + $h_style['t_font_size']) * 2;
    $space = isset($h_style['l_space']) ? $h_style['l_space'] : 1;
    if(isset($h_style['l_font_size']))
      $fixed += ($space + $h_style['l_font_size']) * 2;

    $min_height = 10;
    $ch = $this->getOption('gantt_group_corner_width') ?
      $this->getOption('gantt_group_corner_height') : 0;
    $bw = max($this->getOption('bar_width'), 2);
    $bs = max($this->getOption('bar_space'), 2);
    $bar = max($ch, $bs) + $bw;
    $marker = max($this->getOption('gantt_milestone_size'), 2) * 2;
    $font_size = $min_height;
    if(isset($v_style['t_font_size']))
      $font_size = $v_style['t_font_size'];

    // get largest font size from text classes
    $classes = ['gantt_group:', 'gantt_item:', 'gantt_milestone:'];
    for($i = 0; $i < 20; ++$i) {
      foreach($classes as $cls) {
        $tc = new TextClass($cls . $i);
        $sz = $tc->font_size;
        if($sz > $font_size)
          $font_size = $sz;
      }
    }
    $text = $font_size * 1.5;

    // each row is the biggest of bar, milestone, text label, or fallback value
    $row_height = max($min_height, $bar, $marker, $text);
    $this->height = $fixed + $items * $row_height;
  }

  /**
   * Choose a format for the axis depending on the length in time and pixels
   */
  private function autoFormatAxis($ends)
  {
    // (roughly) measure the horizontal space taken by Y-axis
    $min_space = 1;
    $grid_division = 1;
    $length = $this->height - $this->pad_top - $this->pad_bottom;
    $l_c = $this->getOption('label_centre');
    $factory = $this->getYAxisFactory();
    $axis = $this->createYAxis($factory, $length, $ends, 0, $min_space, $grid_division);
    $display_axis = new DisplayAxis($this, $axis, 0, 'v', 'x', true, $l_c);
    $bbox = $display_axis->measure();
    $left_text = $bbox->x2 - $bbox->x1;

    // approximate length of X-axis
    $length = $this->width - $this->pad_left - $this->pad_right - $left_text;
    $min_space = $this->getOption(['minimum_grid_spacing_h', 0], 'minimum_grid_spacing');
    $want_space = 5; // amount of space wanted between labels
    $good_fit = false;
    $divisions = [
      ['100 year', 'Y'], ['50 year', 'Y'], ['20 year', 'Y'], ['10 year', 'Y\'\s'],

      ['1 year', 'Y'],
      ['6 month', ['M', 'Y']],
      ['3 month', ['M', 'Y']],
      ['2 month', ['M', 'Y']],
      ['1 month', ['M', 'Y']],
      ['14 day', ['d M', 'Y']],
      ['7 day', ['d M', 'Y']],
      ['1 day', ['d', 'M Y']],
      ['1 day', ['D d', 'M Y']],
    ];
    $div_id = count($divisions) - 1;

    $factory = $this->getXAxisFactory();
    $fmt = $div = null;
    while(!$good_fit) {

      // find out how well the division fits
      list($div, $fmt) = $divisions[$div_id];
      $levels = is_array($fmt) ? count($fmt) : 1;
      $this->setOption('datetime_text_format', $fmt);
      $this->setOption('axis_levels_h', $levels);

      $axis = $this->createXAxis($factory, $length, $ends, 0, $min_space, $div);
      if($levels > 1)
        $display_axis = new DisplayAxisLevels($this, $axis, 0, 'h', 'x', true, $l_c);
      else
        $display_axis = new DisplayAxis($this, $axis, 0, 'h', 'x', true, $l_c);

      $overlap = $display_axis->getTextOverlap();
      if($overlap < -$want_space)
        $good_fit = true;

      // give up?
      if($overlap === null || --$div_id < 0) {
        $this->setOption('datetime_text_format', null);
        $this->setOption('axis_levels_h', 1);
        return;
      }
    }

    if($fmt !== null) {
      $this->setOption('datetime_text_format', $fmt);
      $this->setOption('axis_levels_h', $levels);
    }
    if($div !== null)
      $this->setOption('grid_division_h', $div);
    $this->auto_format = false;
  }

  /**
   * Sets up the shading for weekends (or whatever)
   */
  private function getDayShading()
  {
    $shade_days = $this->getOption('gantt_shade_days');
    if(!is_array($shade_days) || empty($shade_days))
      return [];

    // get the values at the axis ends from the axis
    $axis = $this->getAxis('x', null);
    $a_len = $axis->getLength();
    $date = $axis->value(0);
    $end_time = $axis->value($a_len);

    // check how long a day is on this axis
    $timescale = $end_time - $date;
    $days = $timescale / 86400;
    $day_pixels = $a_len / $days;
    if($day_pixels < 1)
      return [];

    // set up a rect or NULL for each day of the week
    $per_day = [];
    for($i = 0; $i < 7; ++$i) {
      if(in_array($i, $shade_days)) {
        $per_day[$i] = [
          'rect', 'x' => 'gl', 'y' => 'gt',
          'width' => 'u1 days', 'height' => 'gh',
          'fill' => $this->getOption(['gantt_shade_days_colour', $i]),
          'opacity' => $this->getOption(['gantt_shade_days_opacity', $i]),
          'stroke' => 'none',
        ];
      } else {
        $per_day[$i] = null;
      }
    }

    // make an array of rects for shading days
    $shapes = [];
    $dd = new \DateTime('@' . new Number($date));
    $dw = $dd->format('w');
    while($date < $end_time) {
      if($per_day[$dw] !== null) {
        $rect = $per_day[$dw];
        $dd = new \DateTime('@' . new Number($date));
        $rect['x'] = 'g' . $dd->format('Y-m-d');
        $shapes[] = $rect;
      }
      $dw = ($dw + 1) % 7;
      $date += 86400;
    }
    return $shapes;
  }

  /**
   * Returns the shape that marks the current day
   */
  protected function getToday()
  {
    $today = $t_i = null;
    $when = $this->getOption('gantt_today_date');
    if($when) {
      $t_i = Graph::dateConvert($when);
      if($t_i !== null)
        $today = new \DateTime('@' . $t_i);
    }
    if($today === null) {
      $today = new \DateTime();
      $t_i = $today->format('U');
    }

    // check that today is on the chart
    $axis = $this->getAxis('x', null);
    $a_len = $axis->getLength();
    $start_time = $axis->value(0);
    $end_time = $axis->value($a_len);

    if($t_i < $start_time || $t_i > $end_time)
      return null;

    $stroke_width = min(10, max(0.1, $this->getOption('gantt_today_width')));
    $dash = $this->getOption('gantt_today_dash');
    $opacity = min(1, max(0, $this->getOption('gantt_today_opacity')));
    if($opacity == 0)
      return null;

    $midday = 'g' . $today->format('Y-m-d') . 'T12:00:00';
    $shape = [
      'line',
      'x1' => $midday, 'x2' => $midday,
      'y1' => 'gt', 'y2' => 'gb',
    ];
    $shape['stroke'] = $this->getOption('gantt_today_colour');
    if($stroke_width != 1)
      $shape['stroke-width'] = $stroke_width;
    if(!empty($dash))
      $shape['stroke-dasharray'] = $dash;
    if($opacity < 1)
      $shape['opacity'] = $opacity;
    return [$shape];
  }

  /**
   * Returns fixed min and max option for an axis
   */
  protected function getFixedAxisOptions($axis, $index)
  {
    $a = $axis == 'y' ? 'h' : 'v';
    $min = $this->getOption(['axis_min_' . $a, $index]);
    $max = $this->getOption(['axis_max_' . $a, $index]);
    if($axis == 'y') {
      if($min !== null) {
        $min = Graph::dateConvert($min);
      } else {

        // need to set a minimum, or it will end up as 1970
        $min = $this->start_date;
      }
      if($max !== null)
        $max = Graph::dateConvert($max);
    }
    return [$min, $max];
  }

  /**
   * Min value is the stored start date
   */
  public function getMinValue()
  {
    return $this->start_date;
  }

  /**
   * Max value is the stored end date
   */
  public function getMaxValue()
  {
    return $this->end_date;
  }

  /**
   * Both axes are X-type for Gantt chart
   */
  protected function getDisplayAxis($axis, $axis_no, $orientation, $type)
  {
    $var = 'main_' . $type . '_axis';
    $main = ($axis_no == $this->{$var});
    $levels = $this->getOption(['axis_levels_' . $orientation, $axis_no]);
    $class = 'Goat1000\SVGGraph\DisplayAxis';
    if(is_numeric($levels) && $levels > 1)
      $class = 'Goat1000\SVGGraph\DisplayAxisLevels';

    return new $class($this, $axis, $axis_no, $orientation, 'x', $main,
      $this->getOption('label_centre'));
  }

  /**
   * Override to pre-calculate axis settings
   */
  protected function getAxisEnds()
  {
    // now is the time to figure out the best format
    if($this->auto_format) {
      $ends = parent::getAxisEnds();
      $this->autoFormatAxis($ends);
    }

    return parent::getAxisEnds();
  }

  /**
   * Override to always return datetime axis
   */
  protected function getXAxisFactory()
  {
    return new AxisFactory(true, $this->settings, false, false, false);
  }

  /**
   * Override to always want bar-style Y axis
   */
  protected function getYAxisFactory()
  {
    // don't reverse the vertical axis for Gantt charts
    return new AxisFactory($this->datetime_keys, $this->settings,
      true, true, false);
  }

  /**
   * Returns an array with x, y, width and height set
   */
  protected function barDimensions($item, $index, $start, $axis, $dataset)
  {
    $bar = [];
    $bar_x = $this->barX($item, $index, $bar, $axis, $dataset);
    if($bar_x === null)
      return [];

    $start = $item->value;
    $value = $item->milestone ? 0 : $item->end - $start;

    // if this is not a milestone, ignore backwards bars
    if($value < 0)
      return [];

    $y_pos = $this->barY($value, $bar, $start, $axis);
    if($y_pos === null)
      return [];
    return $bar;
  }

  /**
   * Returns the SVG code for a bar or milestone
   */
  protected function drawBar(DataItem $item, $index, $start = 0, $axis = null,
    $dataset = 0, $options = [])
  {
    if($item->value === null)
      return '';

    $bar = $this->barDimensions($item, $index, $start, $axis, $dataset);
    if(empty($bar))
      return '';

    // check if this item is off the sides
    $element = $this->getPointer($item, $index, $dataset, $bar);
    if($element) {
      $m = new MarkerShape($element, 'above');
      return $m->draw($this);
    }

    if($item->milestone) {
      if($this->gridX($item->value) === null)
        return null;
      $element = $this->getMilestone($item, $index, $dataset, $bar);
      $label = $item->axis_text ? $item->axis_text : $item->key;
      $label_shown = $this->addDataLabel($dataset, $index, $element, $item,
        $bar['x'], $bar['y'], $element['size'], $bar['height'], $label);
    } else {
      $element = $this->getBar($item, $index, $dataset, $bar);
      $bar_type = $element['element'];
      $bar_content = $element['content'];
      unset($element['element'], $element['content']);

      // data label is % completion
      $complete = new Number($item->complete ? $item->complete : 0);
      $label = "[{$complete}%]";
      $label_shown = $this->addDataLabel($dataset, $index, $element, $item,
        $bar['x'], $bar['y'], $bar['width'], $bar['height'], $label);
    }

    if($this->semantic_classes)
      $element['class'] = 'series' . $dataset;

    if($this->show_tooltips)
      $this->setTooltip($element, $item, $dataset, $item->key, $item->value,
        $label_shown);
    if($this->show_context_menu)
      $this->setContextMenu($element, $dataset, $item, $label_shown);

    if($item->milestone) {
      $m = new MarkerShape($element, 'above');
      return $m->draw($this);
    }
    $bar_part = $this->element($bar_type, $element, null, $bar_content);
    return $this->getLink($item, $item->key, $bar_part);
  }

  /**
   * Returns the incomplete and complete colours for a bar/group
   */
  protected function getBarColours(DataItem $item, $index, $dataset)
  {
    $colour_incomplete = $this->getColour($item, $index, $dataset);
    $colour_complete = $this->getColour($item, $index, $dataset + 1);

    if($item->group) {
      // group bars are coloured differently
      $ci = $this->getItemOption('gantt_group_colour', $dataset, $item, 'colour');
      $cc = $this->getItemOption('gantt_group_colour_complete', $dataset, $item, 'colour_complete');
      if(!empty($ci)) {
        $cg = new ColourGroup($this, $item, $index, $dataset, 'gantt_group_colour', null, 'colour');
        $colour_incomplete = $cg->stroke();
      }
      if(!empty($cc)) {
        $cg = new ColourGroup($this, $item, $index, $dataset + 1, 'gantt_group_colour_complete', null, 'colour_complete');
        $colour_complete = $cg->stroke();
      }
    } else {
      // support fill/fillColour for individual bar complete colours
      $cc = $this->getItemOption('colour_complete', $dataset, $item);
      if(!empty($cc)) {
        $cg = new ColourGroup($this, $item, $index, $dataset + 1, 'colour_complete', null, 'colour_complete');
        $colour_complete = $cg->stroke();
      }
    }
    return [$colour_incomplete, $colour_complete];
  }

  /**
   * Returns the attributes of a bar
   */
  protected function getBar(DataItem $item, $index, $dataset, $bar)
  {
    list($colour_incomplete, $colour_complete) = $this->getBarColours($item, $index, $dataset);
    $round = max($this->getItemOption('bar_round', $dataset, $item), 0);
    $corner_width = $corner_height = 0;
    if($round > 0) {
      // don't allow the round corner to be more than 1/2 bar width or height
      $bar['rx'] = $bar['ry'] = min($round, $bar['width'] / 2, $bar['height'] / 2);
    }

    if($item->group) {
      $corner_width = $this->getItemOption('gantt_group_corner_width', $dataset, $item, 'corner_width');
      $corner_height = $this->getItemOption('gantt_group_corner_height', $dataset, $item, 'corner_height');
    }

    $element =& $bar;

    // group bar has downward pointing corners
    if($corner_height && $corner_width) {

      // make sure the corners are not bigger than the whole bar
      $corner_width = max(0.5, min($corner_width, ($bar['width'] - 2) / 2));

      $path = ['element' => 'path', 'content' => null];
      $inner = $bar['width'] - $corner_width * 2;
      $p = new PathData('M', $bar['x'], $bar['y'] - $corner_height / 2);
      $p->add('h', $bar['width']);
      $p->add('v', $bar['height'] + $corner_height);
      $p->add('l', -$corner_width, -$corner_height);
      $p->add('h', -$inner);
      $p->add('l', -$corner_width, $corner_height);
      $p->add('z');
      $path['d'] = $p;
      $element =& $path;
    } else {

      $bar['element'] = 'rect';
      $bar['content'] = null;
    }
    if($item->complete >= 100) {
      $element['fill'] = $colour_complete;
      $this->setStroke($element, $item, $index, $dataset);
      return $element;
    }

    if($item->complete <= 0) {
      $element['fill'] = $colour_incomplete;
      $this->setStroke($element, $item, $index, $dataset);
      return $element;
    }

    $type = $element['element'];
    unset($element['element'], $element['content']);

    // % complete
    $c = $this->getClippers($bar['x'], $bar['y'], $bar['width'],
      $bar['height'] + $corner_height, $item->complete);
    $bar_parts = '';
    $b1 = $element;
    $b1['fill'] = $colour_complete;
    $b1['clip-path'] = "url(#{$c[0]})";
    $bar_parts .= $this->element($type, $b1);

    // % remaining
    $b2 = $element;
    $b2['fill'] = $colour_incomplete;
    $b2['clip-path'] = "url(#{$c[1]})";
    $bar_parts .= $this->element($type, $b2);

    // outline over top
    $element['fill'] = 'none';
    $this->setStroke($element, $item, $index, $dataset);
    $bar_parts .= $this->element($type, $element);

    return ['element' => 'g', 'content' => $bar_parts];
  }

  /**
   * Returns a pair of clip path IDs for clipping bar
   */
  protected function getClippers($x, $y, $w, $h, $percent)
  {
    $extra = 5;
    $m1 = $w * $percent / 100;
    $m2 = $w - $m1;

    $r = [
      'x' => $x - $extra,
      'y' => $y - $extra,
      'width' => $m1 + $extra,
      'height' => $h + $extra
    ];
    $c = ['id' => $this->newID()];
    $this->defs->add($this->element('clipPath', $c, null,
      $this->element('rect', $r)));
    $clippers = [$c['id']];

    $r['x'] = $x + $m1;
    $r['width'] = $m2 + $extra;
    $c = ['id' => $this->newID()];
    $this->defs->add($this->element('clipPath', $c, null,
      $this->element('rect', $r)));
    $clippers[] = $c['id'];

    return $clippers;
  }

  /**
   * Returns the colour for the milestone
   */
  protected function getMilestoneColour(DataItem $item, $index, $dataset)
  {
    $gpat = !($this->getOption('marker_solid', true));
    $mcolour = $this->getItemOption('gantt_milestone_colour', $dataset, $item, 'colour');
    if(empty($mcolour))
      return $this->getColour(null, $index, $dataset, $gpat, $gpat);

    // support fill and fillColour
    $cg = new ColourGroup($this, $item, $index, $dataset, 'gantt_milestone_colour', null, 'colour');
    $fill = $cg->stroke();

    // impose marker_solid option
    if(!$gpat)
      $fill = new Colour($this, $fill, false, false);
    return $fill;
  }

  /**
   * Returns the attributes of a milestone
   */
  protected function getMilestone(DataItem $item, $index, $dataset, $bar)
  {
    $fill = $this->getMilestoneColour($item, $index, $dataset);
    $size = max(2, $this->getItemOption('gantt_milestone_size', $dataset, $item, 'size'));
    $type = $this->getItemOption('gantt_milestone_type', $dataset, $item, 'type');

    $marker = [
      'type' => $type,
      'x' => $bar['x'],
      'y' => $bar['y'] + $bar['height'] / 2,
      'fill' => $fill,
      'size' => $size,
    ];
    $this->setStroke($marker, $item, $index, $dataset);
    return $marker;
  }

  /**
   * Returns an arrow pointing to where the data is off the display, or null if it is not
   */
  protected function getPointer(DataItem $item, $index, $dataset, $bar)
  {
    $angle = 0;
    $pos_start = $this->gridX($item->value);
    $pos_end = $item->milestone ? $pos_start : $this->gridX($item->end);

    $size = $bar['height'] / 2;
    $offset = 3;
    if($pos_start > $this->width - $this->pad_right) {
      $x = $this->width - $this->pad_right - $size - $offset;
      $angle = 90;
    }
    if($pos_end < $this->pad_left) {
      $x = $this->pad_left + $size + $offset;
      $angle = 270;
    }

    if($angle === 0)
      return null;

    if($item->milestone) {
      $fill = $this->getMilestoneColour($item, $index, $dataset);
    } else {
      $colours = $this->getBarColours($item, $index, $dataset);
      $fill = $item->complete >= 100 ? $colours[1] : $colours[0];
    }

    $marker = [
      'type' => 'triangle',
      'x' => $x,
      'y' => $bar['y'] + $bar['height'] / 2,
      'fill' => $fill,
      'size' => $size,
      'angle' => $angle,
    ];
    $this->setStroke($marker, $item, $index, $dataset);
    return $marker;
  }

  /**
   * Tooltips are a little more complicated on Gantt chart
   */
  protected function formatTooltip(&$item, $dataset, $key, $value)
  {
    $axis = $this->x_axes[$this->main_x_axis];
    $format = $this->getOption('tooltip_datetime_format');

    $dt = new \DateTime('@' . $item->value);
    $text_start = $axis->format($dt, $format);
    if($item->milestone) {
      $ttext = $item->axis_text ? $item->axis_text : $key;
      $ttext .= "\n" . $text_start;
      return $ttext;
    }

    $dte = new \DateTime('@' . $item->end);
    $text_end = $axis->format($dte, $format);
    $ttext = "{$text_start} - {$text_end}";
    if($this->getOption('gantt_tooltip_duration')) {
      $days = ceil(($item->end - $item->value) / 86400);
      if($days > 364) {
        $years = $days / 365;
        $ttext .= "\n" . new Number($years) . " years";
      } elseif($days > 20) {
        $weeks = floor($days / 7);
        $days = $days % 7;
        $ttext .= "\n" . new Number($weeks) . " weeks";
        if($days) {
          $ttext .= ", " . new Number($days) . " day";
          if($days != 1)
            $ttext .= 's';
        }
      } else {
        $ttext .= "\n" . new Number($days) . " day";
        if($days != 1)
          $ttext .= 's';
      }
    }

    if($item->complete && $this->getOption('gantt_tooltip_complete')) {
      $n = new Number(min(100, $item->complete));
      $ttext .= "\n[{$n}% complete]";
    }

    return $ttext;
  }

  /**
   * Returns TRUE if the item is visible on the graph
   */
  public function isVisible($item, $dataset = 0)
  {
    if($item->value === null)
      return false;
    if($item->milestone)
      return true;
    return ($item->end - $item->value != 0);
  }
}
