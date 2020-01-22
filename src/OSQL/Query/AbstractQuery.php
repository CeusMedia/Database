<?php
/**
 *	Abstract query class.
 *
 *	Copyright (c) 2010-2020 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\OSQL\Query;

use CeusMedia\Database\OSQL;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Condition\Group;
use CeusMedia\Database\OSQL\Query\QueryInterface;
use CeusMedia\Database\OSQL\Table;

/**
 *	Abstract query class.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2020 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
abstract class AbstractQuery
{
	protected $conditions	= array();
	protected $joins		= array();
	protected $fields;
	protected $limit;
	protected $offset;
	protected $query;

	public $timing			= array(
		'render'	=> 0,
		'prepare'	=> 0,
		'execute'	=> 0,
		'total'		=> 0,
	);

	const JOIN_TYPE_NATURAL	= 0;
	const JOIN_TYPE_LEFT	= 1;
	const JOIN_TYPE_RIGHT	= 2;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		Client		$dbc	OSQL database connection
	 *	@return		void
	 */
	public function __construct( Client $dbc )
	{
		$this->dbc	= $dbc;
	}

	/**
	 *	Static constructor.
	 *	@access		public
	 *	@param		Client		$dbc	OSQL database connection
	 *	@return		self
	 */
	public static function create( Client $dbc ): self
	{
		return new static( $dbc );
	}

	/**
	 *	Sends query to assigned client for execution and returns response.
	 *	@access		public
	 *	@return		mixed
	 */
	public function execute()
	{
		return $this->dbc->execute( $this );
	}

#	abstract protected function checkSetup();

	abstract public function render(): object;

	/**
	 *
	 *	@access		public
	 *	@param		Condition|Group	$condition		Condition object
	 *	@return		self
	 */
	public function where( $condition ): self
	{
		return $this->and( $condition );
	}

	/**
	 *
	 *	@access		public
	 *	@param		Condition|Group	$condition		Condition object
	 *	@return		self
	 */
	public function and( $condition ): self
	{
		$this->conditions[]	= [
			'operation'	=> Group::OPERATION_AND,
			'condition'	=> $condition,
		];
		return $this;
	}

	/**
	 *
	 *	@access		public
	 *	@param		Condition|Group	$condition		Condition object
	 *	@return		self
	 */
	 public function or( $condition ): self
	 {
		if( !$this->conditions )
			throw new \Exception( 'No condition set yet' );
		$this->conditions[]	= [
			'operation'	=> Group::OPERATION_OR,
			'condition'	=> $condition,
		];
		return $this;
	}

	/**
	 *
	 *	@access		public
	 *	@return		QueryInterface
	 */
	public function join( Table $table, string $keyLeft, ?string $keyRight = NULL, ?int $type = self::JOIN_TYPE_NATURAL ): QueryInterface
	{
		$this->joins[]	= (object) [
			'table'		=> $table,
			'left'		=> $keyLeft,
			'right'		=> $keyRight,
			'type'		=> $type,
		];
		return $this;
		array_push( $this->tables, $lastTable );
		return $this;
	}

	public function leftJoin( Table $table, string $keyLeft, ?string $keyRight = NULL ): QueryInterface
	{
		return $this->join( $table, $keyLeft, $keyRight, static::JOIN_TYPE_LEFT );
	}

	public function rightJoin( Table $table, string $keyLeft, ?string $keyRight = NULL ): QueryInterface
	{
		return $this->join( $table, $keyLeft, $keyRight, static::JOIN_TYPE_RIGHT );
	}

	/**
	 *	Sets limit.
	 *	@access		public
	 *	@throws		\InvalidArgumentException	if limit is not an integer
	 *	@throws		\InvalidArgumentException	if limit is not greater than 0
	 *	@return		self
	 */
	public function limit( $limit = NULL ): self
	{
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
	 *	Sets
	 *	@access		public
	 *	@throws		\InvalidArgumentException	if limit is not an integer
	 *	@throws		\InvalidArgumentException	if limit is not greater than 0
	 *	@return		self
	 */
	public function offset( $offset = NULL ): self
	{
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

	//  --  PROTECTED  --  //

	/**
	 *
	 *	@access		protected
	 *	@param		array		$parameters		Reference to parameters map
	 *	@return		string
	 */
	protected function renderJoins(): string
	{
		if( !$this->joins )
			return '';
		$list	= array();
		foreach( $this->joins as $join ){
			$prefix	= '';
			if( $join->type === static::JOIN_TYPE_LEFT )
				$prefix	= 'LEFT ';
			else if( $join->type === static::JOIN_TYPE_RIGHT )
				$prefix	= 'RIGHT ';
			$specification	= ' USING ('.$join->left.')';
			if( $join->right )
				$specification	= ' ON '.$join->left.' = '.$join->right;
			$tableName	= $join->table->render();
			$list[]	= ' '.$prefix.'JOIN '.$tableName.$specification;
		}
		return implode( ' ', $list );
	}

	/**
	 *
	 *	@access		protected
	 *	@param		array		$parameters		Reference to parameters map
	 *	@return		string
	 */
	protected function renderConditions( & $parameters ): string
	{
		if( !$this->conditions )
			return '';
		$list	= array();
		foreach( $this->conditions as $condition ){
			if( $list )
				$list[]	= $condition['operation'];
			$list[]	= $condition['condition']->render( $parameters );
		}
		return ' WHERE '.implode( ' ', $list );
	}

	/**
	 *
	 *	@access		protected
	 *	@param		array		$parameters		Reference to parameters map
	 *	@return		string
	 */
	protected function renderLimit( & $parameters ): string
	{
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
	 *	@return		string
	 */
	protected function renderOffset( & $parameters ): string
	{
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
