<?php
/**
 *	Abstract query class.
 *
 *	Copyright (c) 2010-2011 Christian Würker (ceusmedia.de)
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2015 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\OSQL\Query;
/**
 *	Abstract query class.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2015 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
abstract class \CeusMedia\Database\OSQL\Query\Abstract implements \CeusMedia\Database\OSQL\Query{

	protected $conditions	= array();
	protected $fields;
	protected $limit;
	protected $offset;
	protected $query;
	public $timeExecute	= NULL;
	public $timePrepare	= NULL;
	public $timeRender	= NULL;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		\CeusMedia\Database\OSQL\Client		$dbc	OSQL database connection
	 *	@return		void
	 */
	public function __construct( \CeusMedia\Database\OSQL\Client $dbc ){
		$this->dbc	= $dbc;
	}

	/**
	 *
	 *	@access		public
	 *	@return		void
	 */
	public function execute(){
		return $this->dbc->execute( $this );
	}

#	abstract protected function checkSetup();

	abstract public function render();

	/**
	 *
	 *	@access		public
	 *	@param		CMM_OSQL_Condition	$conditions		Condition object
	 *	@return		void
	 */
	public function where( \CeusMedia\Database\OSQL\Condition $conditions ){
		return $this->andWhere( $conditions );
	}

	/**
	 *
	 *	@access		public
	 *	@param		CMM_OSQL_Condition	$conditions		Condition object
	 *	@return		void
	 */
	public function andWhere( \CeusMedia\Database\OSQL\Condition $conditions ){
		$this->conditions[]	= $conditions;
		return $this;
	}

	/**
	 *
	 *	@access		public
	 *	@param		CMM_OSQL_Condition	$conditions		Condition object
	 *	@return		void
	 */
	public function orWhere( \CeusMedia\Database\OSQL\Condition $conditions ){
		if( !$this->conditions )
			throw new \Exception( 'No condition set yet' );
		$last	= array_pop( $this->conditions );
		$last->join( $conditions );
		return $this->andWhere( $last );
	}

	/**
	 *
	 *	@access		public
	 *	@return		void
	 */
	public function join( \CeusMedia\Database\OSQL\Table $table, $keyLeft, $keyRight ){
		array_push( $this->tables, $lastTable );
		return $this;
	}

	/**
	 *
	 *	@access		public
	 *	@return		void
	 */
	public function limit( $limit = NULL ){
		if( !is_null( $limit ) ){
			if( !is_int( $limit ) )
				throw new \InvalidArgumentException( 'Must be integer or NULL' );
			if( $limit <= 0 )
				throw new \InvalidArgumentException( 'Must greater than 0' );
			$this->limit	= $limit;
		}
		else
			$this->limit	= NULL;
		return $this;
	}

	/**
	 *
	 *	@access		public
	 *	@return		void
	 */
	public function offset( $offset = NULL ){
		if( !is_null( $offset ) ){
			if( !is_int( $offset ) )
				throw new \InvalidArgumentException( 'Must be integer or NULL' );
			if( $offset <= 0 )
				throw new \InvalidArgumentException( 'Must greater than 0' );
			$this->offset	= $offset;
		}
		else
			$this->offset	= NULL;
		return $this;
	}

	/**
	 *
	 *	@access		protected
	 *	@param		array		$parameters		Reference to parameters map
	 *	@return		void
	 */
	protected function renderConditions( & $parameters ){
		if( !$this->conditions )
			return '';
		$list	= array();
		foreach( $this->conditions as $condition )
			$list[]	= $condition->render( $parameters );
		return ' WHERE '.implode( ' AND ', $list );
	}

	/**
	 *
	 *	@access		protected
	 *	@param		array		$parameters		Reference to parameters map
	 *	@return		void
	 */
	protected function renderLimit( & $parameters ){
		if( !$this->limit )
			return '';
		$limit		= ' LIMIT :limit';
		$parameters['limit']	= array(
			'type'	=> \PDO::PARAM_INT,
			'value'	=> $this->limit,
		);
		return $limit;
	}

	/**
	 *
	 *	@access		protected
	 *	@param		array		$parameters		Reference to parameters map
	 *	@return		void
	 */
	protected function renderOffset( & $parameters ){
		if( !$this->offset )
			return '';
		$offset		= ' OFFSET :offset';
		$parameters['offset']	= array(
			'type'	=> \PDO::PARAM_INT,
			'value'	=> $this->offset,
		);
		return $offset;
	}
}
?>
