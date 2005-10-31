<?php
/***************************************************************************
 *   Copyright (C) 2005 by Anton Lebedevich, Konstantin V. Arkhipov        *
 *   voxus@gentoo.org                                                      *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * Reference for calling built-in database functions.
	**/
	final class SQLFunction extends Castable implements DialectString
	{
		private $name	= null;
		private $alias	= null;

		private $args	= array();
		
		public static function create($name /* , ... */)
		{
			if (func_num_args() > 1) {
				$args = func_get_args();
				array_shift($args);
				return new SQLFunction($name, $args);
			} else
				return new SQLFunction($name);
		}
		
		public function __construct($name /* , ... */)
		{
			$this->name = $name;
			
			if (func_num_args() > 1) {
				$args = func_get_args();
				
				if (is_array($args[1]))
					$this->args = $args[1];
				else {
					array_shift($args);
					$this->args = $args;
				}
			}
		}
		
		public function setAlias($alias)
		{
			$this->alias = $alias;
			
			return $this;
		}
		
		public function toString(Dialect $dialect)
		{
			$args = array();

			if ($this->args) {
				foreach ($this->args as &$arg)
					if ($arg instanceof DBValue)
						$args[] = $arg->toString($dialect);
					// we're not using * anywhere but COUNT()
					elseif ($arg === '*')
						$args[] = $dialect->quoteValue($arg);
					else
						$args[] = $dialect->fieldToString($arg);
			}
			
			$out =
				$this->name.'('
				.($args == array() ? '' : implode(', ', $args))
				.')';
			
			$out =
				$this->cast
					? $dialect->toCasted($out, $this->cast)
					: $out;
			
			return 
				$this->alias
					? "{$out} AS {$this->alias}"
					: $out;
		}
	}
?>