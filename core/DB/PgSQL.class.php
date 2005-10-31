<?php
/***************************************************************************
 *   Copyright (C) 2004-2005 by Konstantin V. Arkhipov                     *
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
	 * PostgreSQL DB connector.
	 *
	 * @link		http://www.postgresql.org/
	**/
	class PgSQL extends DB
	{
		private static $dialect = null;
		
		public function __construct()
		{
			self::$dialect = new PostgresDialect();
		}
		
		public static function getDialect()
		{
			return self::$dialect;
		}
		
		public function isBusy()
		{
			return pg_connection_busy($this->link);
		}
		
		public function asyncQuery(Query $query)
		{
			return pg_send_query(
				$this->link, $query->toString($this->getDialect())
			);
		}

		public function connect(
			$user, $pass, $host,
			$base = null, $persistent = false
		)
		{
			$port = null;
			
			if (strpos($host, ':') !== false)
				list($host, $port) = explode(':', $host, 2);
			
			$conn =
				"host={$host} user={$user}"
				.($pass ? " password={$pass}" : '')
				.($base ? " dbname={$base}" : '')
				.($port ? " port={$port}" : '');

			if ($persistent === true)
				$this->link = pg_pconnect($conn);
			else 
				$this->link = pg_connect($conn);

			$this->persistent = $persistent;

			if (!$this->link)
				throw new DatabaseException(
					'can not connect to PostgreSQL server: '.pg_errormessage()
				);

			return $this;
		}
		
		public function disconnect()
		{
			if ($this->isConnected())
				pg_close($this->link);

			return $this;
		}
		
		public function isConnected()
		{
			return is_resource($this->link);
		}
		
		/**
		 * misc
		**/
		
		public function obtainSequence($sequence)
		{
			$res = $this->queryRaw("select nextval('{$sequence}') as seq");
			$row = pg_fetch_assoc($res);
			pg_free_result($res);
			return $row['seq'];
		}

		public function setEncoding($encoding)
		{
			return pg_set_client_encoding($this->link, $encoding);
		}
		
		/**
		 * query methods
		**/
		
		public function queryRaw($queryString)
		{
			//	echo $queryString.'<hr>'; flush();
			//	error_log($queryString);
			try {
				return pg_query($this->link, $queryString);
			} catch (BaseException $e) {
				throw new DatabaseException(
					pg_errormessage($this->link).' - '.$queryString
				);
			}
		}

		/**
		 * Same as query, but returns number of affected rows
		 * Returns number of affected rows in insert/update queries
		 *
		 * @param	Query
		 * @access	public
		 * @return	integer
		**/
		public function queryCount(Query $query)
		{
			return pg_affected_rows($this->query($query));
		}
		
		public function queryObjectRow(Query $query, GenericDAO $dao)
		{
			$res = $this->query($query);
			
			if ($res) {
				if (pg_num_rows($res) > 1)
					throw new DatabaseException(
						"query returned too many rows (we need only one) : "
						.$query->toString($this->getDialect())
					);

				if ($row = pg_fetch_assoc($res)) {
					pg_free_result($res);
					return $dao->makeObject($row);
				} else 
					pg_free_result($res);
			}

			return null;
		}
		
		public function queryRow(Query $query)
		{
			$res = $this->query($query);
			
			if ($res) {
				$ret = pg_fetch_assoc($res);
				pg_free_result($res);
				return $ret;
			} else
				return null;
		}
		
		public function queryObjectSet(Query $query, GenericDAO $dao)
		{
			$res = $this->query($query);
			
			if ($res) {
				$array = array();
				
				while ($row = pg_fetch_assoc($res))
					$array[] = $dao->makeObject($row);
				
				pg_free_result($res);
				return $array;
			}
			
			return null;
		}
		
		public function queryColumn(Query $query)
		{
			$res = $this->query($query);
			
			if ($res) {
				$array = array();

				while ($row = pg_fetch_row($res))
					$array[] = $row[0];

				pg_free_result($res);
				return $array;
			} else
				return null;
		}
		
		public function querySet(Query $query)
		{
			$res = $this->query($query);
			
			if ($res) {
				$array = array();

				while ($row = pg_fetch_assoc($res))
					$array[] = $row;

				pg_free_result($res);
				return $array;
			} else
				return null;
		}
	}
?>