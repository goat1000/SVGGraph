<?php
/**
 * Copyright (C) 2019-2020 Graham Breach
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
 * Implements MultiGraph setup and overrides some Graph functions
 */
trait MultiGraphTrait {

  /**
   * Can't actually define it here because it could be defined multiple times,
   * causing E_STRICT error
   */
  // protected $multi_graph = null;

  /**
   * Construct MultiGraph when setting values
   */
  public function values($values)
  {
    parent::values($values);
    if(!$this->values->error) {
      $this->multi_graph = new MultiGraph($this->values,
        $this->getOption('force_assoc'),
        $this->datetime_keys,
        $this->getOption('require_integer_keys'));

      $this->multi_graph->setEnabledDatasets($this->getOption('dataset'));
    }
  }

  public function getMinValue()
  {
    return $this->multi_graph->getMinValue();
  }

  public function getMaxValue()
  {
    return $this->multi_graph->getMaxValue();
  }

  public function getMinKey()
  {
    return $this->multi_graph->getMinKey();
  }

  public function getMaxKey()
  {
    return $this->multi_graph->getMaxKey();
  }

  public function getKey($i)
  {
    return $this->multi_graph->getKey($i);
  }

  protected function setup()
  {
    $dataset_count = count($this->multi_graph);
    $this->colourSetup($this->multi_graph->itemsCount(-1), $dataset_count);
  }

}

