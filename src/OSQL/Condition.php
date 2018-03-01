<?php
/**
 *	...
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
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2018 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\OSQL;
/**
 *	...
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2018 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Condition{
	protected $type			= NULL;
	protected $fieldName	= NULL;
	protected $operation	= '=';
	protected $value		= NULL;
	protected $joins		= array();

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		CMM_OSQL_Client_Abstract	$dbc	OSQL database connection
	 *	@param		string		$fieldName		Column name
	 *	@param		mixed		$value			Value to match
	 *	@param		string		$operation		Comparison operation
	 *	@return		void
	 */
	public function __construct( $fieldName = NULL, $value = NULL, $operation = NULL ){
		if( $fieldName )
			$this->setFieldName( $fieldName );
		if( $operation )
			$this->setOperation( $operation );
		if( $value )
			$this->setValue( $value );
	}

	/**
	 *	Gets column name.
	 *	@access		public
	 *	@return		string
	 */
	public function getFieldName(){
		return $this->name;
	}

	/**
	 *	Returns comparison operation.
	 *	@access		public
	 *	@return		string
	 */
	public function getOperation(){
		return $this->operation;
	}

	/**
	 *	Get value to match.
	 *	@access		public
	 *	@return		mixed
	 */
	public function getValue(){
		return $this->value;
	}

	/**
	 *	...
	 *	@access		public
	 *	@return		void
	 */
	public function join( \CeusMedia\Database\OSQL\Condition $condition ){
		$this->joins[]	= $condition;
	}

	/**
	 *	Returns rendered SQL condition string and a map of parameters for parameter binding.
	 *	@access		public
	 *	@return		array
	 */
	public function render( & $parameters ){
		$counter	= 0;
		do{
			$key	= 'condition_'.str_replace( '.', '_', $this->name ).'_'.$counter;
			$counter++;
		}
		while( isset( $parameters[$key] ) );
		$parameters[$key]	= array(
			'type'	=> $this->type,
			'value'	=> $this->value
		);
		$condition	= $this->name.' '.$this->operation.' :'.$key;

		if( !$this->joins )
			return $condition;
		$joins	= array( $condition );
		foreach( $this->joins as $join )
			$joins[]	= $join->render( $parameters );
		return '( '.implode( ' OR ', $joins ).' )';
	}

	/**
	 *	Sets column name.
	 *	@access		public
	 *	@return		void
	 */
	public function setFieldName( $fieldName ){
		$this->name		= $fieldName;
	}

	/**
	 *	Sets equality operator for comparison operation.
	 *	Allowed: = | < | <= | > | >= | != | IS | IS NOT
	 *	@access		public
	 *	@param		string		$operation		Operator between column key and value.
	 *	@return		void
	 */
	public function setOperation( $operation ){
		$this->operation	= $operation;
	}

	/**
	 *	Sets value to match.
	 *	@access		public
	 *	@return		void
	 */
	public function setValue( $value ){
		$this->value	= $value;
	}
}
?>
