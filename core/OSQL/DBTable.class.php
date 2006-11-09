<?php
/***************************************************************************
 *   Copyright (C) 2006 by Konstantin V. Arkhipov                          *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * @ingroup OSQL
	**/
	final class DBTable implements DialectString
	{
		private $name		= null;
		
		private $columns	= array();
		private $order		= array();
		
		public static function create($name)
		{
			return new self($name);
		}
		
		public function __construct($name)
		{
			$this->name = $name;
		}
		
		public function getColumns()
		{
			return $this->columns;
		}
		
		public function addColumn(DBColumn $column)
		{
			$name = $column->getName();
			
			if (isset($this->columns[$name]))
				throw new WrongArgumentException(
					"column '{$name}' already exist"
				);
			
			$this->order[] = $this->columns[$name] = $column;
			
			$column->setTable($this);
			
			return $this;
		}
		
		public function getColumnByName($name)
		{
			if (!isset($this->columns[$name]))
				throw new MissingElementException(
					"column '{$name}' does not exist"
				);
			
			return $this->columns[$name];
		}
		
		public function dropColumnByName($name)
		{
			if (!isset($this->columns[$name]))
				throw new MissingElementException(
					"column '{$name}' does not exist"
				);
			
			unset($this->columns[$name]);
			unset($this->order[array_search($name, $this->order)]);
			
			return $this;
		}
		
		public function getName()
		{
			return $this->name;
		}
		
		public function getOrder()
		{
			return $this->order;
		}
		
		public function toDialectString(Dialect $dialect)
		{
			return OSQL::createTable($this)->toDialectString($dialect);
		}
		
		// TODO: consider port to AlterTable class (unimplemented yet)
		public static function findDifferences(
			Dialect $dialect,
			DBTable $source,
			DBTable $target
		)
		{
			$out = array();
			
			$head = 'ALTER TABLE '.$dialect->quoteTable($target->getName());
			
			$sourceColumns = $source->getColumns();
			$targetColumns = $target->getColumns();
			
			foreach ($sourceColumns as $name => $column) {
				if (isset($targetColumns[$name])) {
					if (
						$column->getType()->getId()
						!= $targetColumns[$name]->getType()->getId()
					) {
						$out[] =
							$head
							.' ALTER COLUMN '.$dialect->quoteField($name)
							.' TYPE '.$targetColumns[$name]->getType()->toString()
							.';';
					}
					
					if (
						$column->getType()->isNull()
						!= $targetColumns[$name]->getType()->isNull()
					) {
						$out[] =
							$head
							.' ALTER COLUMN '.$dialect->quoteField($name)
							.' '
							.(
								$targetColumns[$name]->getType()->isNull()
									? 'DROP'
									: 'SET'
							)
							.' NOT NULL;';
					}
				} else {
					$out[] =
						$head
						.' DROP COLUMN '.$dialect->quoteField($name).';';
				}
			}
			
			foreach ($targetColumns as $name => $column) {
				if (!isset($sourceColumns[$name])) {
					$out[] =
						$head
						.' ADD COLUMN '
						.$column->toDialectString($dialect).';';
				}
			}
			
			return $out;
		}
	}
?>