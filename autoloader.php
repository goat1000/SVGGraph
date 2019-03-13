<?php
/**
 * Copyright (C) 2019 Graham Breach
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
 * PSR-4 autoloader
 *
 * For more information, please contact <graham@goat1000.com>
 */
spl_autoload_register(function($class) {

  // check class starts with namespace
  $ns = 'Goat1000\\SVGGraph\\';
  if(strpos($class, $ns) !== 0)
    return;

  $local_class = substr($class, strlen($ns));
  $filename = __DIR__ . DIRECTORY_SEPARATOR .
    str_replace('\\', DIRECTORY_SEPARATOR, $local_class) . '.php';

  // if the file exists, load it
  if(file_exists($filename))
    require $filename;

  // not found, fail silently
});

