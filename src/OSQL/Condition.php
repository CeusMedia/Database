<?php
/**
 *	...
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

/**
 *	...
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class Condition
{
	protected $type			= NULL;
	protected $fieldName	= NULL;
	protected $operation	= '=';
	protected $value		= NULL;

	/**
	 *	Constructor.
	 *	@access		public
	 *	@param		string		$fieldName		Column name
	 *	@param		mixed		$value			Value to match
	 *	@param		string		$operation		Comparison operation
	 *	@return		void
	 */
	public function __construct( $fieldName = NULL, $value = NULL, $operation = NULL )
	{
		if( $fieldName )
			$this->setFieldName( $fieldName );
		if( $operation )
			$this->setOperation( $operation );
		if( $value !== NULL )
			$this->setValue( $value );
	}

	/**
	 *	Gets column name.
	 *	@access		public
	 *	@return		string
	 */
	public function getFieldName(): ?string
	{
		return $this->name;
	}

	/**
	 *	Returns comparison operation.
	 *	@access		public
	 *	@return		string
	 */
	public function getOperation(): string
	{
		return $this->operation;
	}

	/**
	 *	Get value to match.
	 *	@access		public
	 *	@return		mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 *	Returns rendered SQL condition string and writes to a map of parameters for parameter binding.
	 *	@access		public
	 *	@param		array		$parameters		Reference to parameters map
	 *	@return		string
	 */
	public function render( & $parameters ): string
	{
		$counter	= 0;

		do{
			$key	= 'c_'.preg_replace( '/[^a-z0-9]/i', '_', $this->name ).'_'.$counter;
			$counter++;
		}
		while( isset( $parameters[$key] ) );

		if( in_array( $this->type, ['array', 'object'] ) ){
			$keyList	= [];
			foreach( $this->value as $value ){
				$keyList[]	= ':'.$key;
				$parameters[$key]	= array(
					'type'	=> gettype( $value ),
					'value'	=> $value
				);
				$key	= 'c_'.preg_replace( '/[^a-z0-9]/i', '_', $this->name ).'_'.$counter;
				$counter++;
			}
			$condition	= $this->name.' '.$this->operation.' ('.implode( ',', $keyList ).')';
		}
		else{
			$parameters[$key]	= array(
				'type'	=> $this->type,
				'value'	=> $this->value
			);
			$condition	= $this->name.' '.$this->operation.' :'.$key;
		}
		return $condition;
	}

	/**
	 *	Sets column name.
	 *	@access		public
	 *	@param		string		$fieldName		Column name
	 *	@return		self
	 */
	public function setFieldName( string $fieldName ): self
	{
		$this->name		= $fieldName;
		return $this;
	}

	/**
	 *	Sets equality operator for comparison operation.
	 *	Allowed: = | < | <= | > | >= | != | IS | IS NOT
	 *	@access		public
	 *	@param		string		$operation		Operator between column key and value.
	 *	@return		self
	 */
	public function setOperation( string $operation ): self
	{
		$this->operation	= $operation;
		return $this;
	}

	/**
	 *	Sets value to match.
	 *	@access		public
	 *	@param		mixed		$value			Value to match
	 *	@return		self
	 */
	public function setValue( $value ): self
	{
		$type	= gettype( $value );
		if( in_array( $type, ['object', 'resource', 'resource (closed)'] ) )
			throw new \InvalidArgumentException( 'Value of type "'.$type.'" is not allowed' );
		$this->value	= $value;
		$this->type		= $type;
		return $this;
	}
}
