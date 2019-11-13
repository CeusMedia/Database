<?php
/**
 *	Abstract query class.
 *
 *	Copyright (c) 2010-2019 Christian Würker (ceusmedia.de)
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
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\OSQL;

use CeusMedia\Database\OSQL\QueryInterface;

/**
 *	Abstract query class.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
abstract class QueryAbstract
{
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
	 *
	 *	@access		public
	 *	@return		void
	 */
	public function execute()
	{
		return $this->dbc->execute( $this );
	}

#	abstract protected function checkSetup();

	abstract public function render(): array;

	/**
	 *
	 *	@access		public
	 *	@param		Condition	$conditions		Condition object
	 *	@return		self
	 */
	public function where( Condition $conditions ): self
	{
		return $this->andWhere( $conditions );
	}

	/**
	 *
	 *	@access		public
	 *	@param		Condition	$conditions		Condition object
	 *	@return		self
	 */
	public function andWhere( Condition $conditions ): self
	{
		$this->conditions[]	= $conditions;
		return $this;
	}

	/**
	 *
	 *	@access		public
	 *	@param		Condition	$conditions		Condition object
	 *	@return		self
	 */
	public function orWhere( Condition $conditions ): self
	{
		if( !$this->conditions )
			throw new \Exception( 'No condition set yet' );
		$last	= array_pop( $this->conditions );
		$last->join( $conditions );
		return $this->andWhere( $last );
	}

	/**
	 *
	 *	@access		public
	 *	@return		QueryInterface
	 */
	public function join( Table $table, string $keyLeft, string $keyRight ): QueryInterface
	{
		array_push( $this->tables, $lastTable );
		return $this;
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
	protected function renderConditions( & $parameters ): string
	{
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
?>
