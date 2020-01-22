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
 *	@package		CeusMedia_Database_DAO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
namespace CeusMedia\Database\DAO;

/**
 *	...
 *	@category		Library
 *	@package		CeusMedia_Database_DAO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2019 Christian Würker
 *	@license		http://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
abstract class Table
{
	protected $name;
	protected $prefix;
	protected $primaryKey;
	protected $indices		= array();
	protected $isValid		= FALSE;
	protected $modelClass;

	public function __construct( $connection )
	{
		$this->connection	= $connection;
	}

	public function checkField( $name )
	{
		if( !in_array( $name, $this->fields ) )
			throw new \InvalidArgumentException( 'Field "'.$this->name.":".$name.'" is not defined' );
	}

	public function getById( $primaryKey )
	{
		return $this->getByIndex( $this->primaryKey, $primaryKey );

		$this->validateSetup();
		$query	= 'SELECT * FROM '.$this->prefix.$this->name.' WHERE '.$this->primaryKey.' = :id';
		$stmt	= $this->connection->prepare( $query );
		$class	= new \ReflectionClass( $this->modelClass );
		$model	= $class->newInstanceArgs( array( $this ) );
		$stmt->setFetchMode( \PDO::FETCH_INTO, $model );
		$stmt->bindParam( ':id', $primaryKey );

		if( !$stmt->execute() ){
			$info	= $stmt->errorInfo();
			throw new \Exception( $info[2], $info[1] );
		}
		$dao	= $stmt->fetch();
		return $dao;
	}

	public function getByIndex( $name, $value )
	{
		$list	= $this->indexByIndex( $name, $value, 1 );
		if( !$list )
			return NULL;
		return array_shift( $list );
	}

	public function getFieldNames()
	{
		return $this->fields;
	}

	public function indexByIndex( $name, $value, $limit = NULL, $offset = NULL )
	{
		return $this->indexByCondition( $name, '=', $value, $limit, $offset );
	}

	public function checkOperation( $operation )
	{
	}

	public function indexByCondition( $name, $operation, $value, $limit = NULL, $offset = NULL )
	{
		$conditions	= array(
			'name'		=> $name,
			'operation'	=> $operation,
			'value'		=> $value,
		);
		return $this->indexByConditions( array( $conditions ), $limit, $offset );
	}

	public function indexByConditions( $conditions, $limit = NULL, $offset = NULL )
	{
		$this->validateSetup();
		$list	= array();
		$params	= array();
		$from	= 'FROM '.$this->prefix.$this->name;
		foreach( $conditions as $condition ){
			$name	= $condition['name'];
			$this->checkField( $name );
			$op		= empty( $condition['operation'] ) ? '=' : $condition['operation'];
			$value	= empty( $condition['value'] ) ? NULL : $condition['value'];
			$params[$name]	= array( 'type' => \PDO::PARAM_STR, 'value' => $value );
			$list[]	= $name.' '.$op.' :'.$name;
		}
		if( $limit ){
			$params['limit']	= array(
				'type'	=> \PDO::PARAM_INT,
				'value'	=> abs( (int) $limit )
			);
			$limit	= ' LIMIT :limit';
		}
		if( $offset ){
			$params['offset']	= array(
				'type'	=> \PDO::PARAM_INT,
				'value'	=> abs( (int) $offset )
			);
			$offset	= ' OFFSET :offset';
		}
		$where	= NULL;
		if( $conditions )
			$where	= 'WHERE '.implode( ' AND ', $list );
		$query	= 'SELECT * '.$from.' '.$where.$limit.$offset.';';
#	remark( $query );
		$stmt	= $this->connection->prepare( $query );
		$class	= new \ReflectionClass( $this->modelClass );
		$model	= $class->newInstanceArgs( array( $this ) );
		$stmt->setFetchMode( \PDO::FETCH_INTO, $model );
		foreach( $params as $name => $param ){
#	remark( "bind: ".$name." => [".$param['type']."] ".$param['value'] );
			$stmt->bindParam( $name, $param['value'], $param['type'] );
		}
		if( !$stmt->execute() ){
			$info	= $stmt->errorInfo();
			throw new \Exception( $info[2], $info[1] );
		}
		return $stmt->fetchAll();
	}

	public function index( $limit = NULL, $offset = NULL )
	{
		return $this->indexByConditions( array(), $limit, $offset );
	}

	public function getConnection()
	{
		return $this->connection;
	}

	protected function find()
	{
		while( $row = $stmt->fetch() ){
			var_dump( $row );
		}
	}

	public function updateById( $id, $fields )
	{
		$this->validateSetup();
		$this->connection->beginTransaction();
		try{
			foreach( $fields as $name => $value ){
				$query	= 'UPDATE '.$this->prefix.$this->name.' SET '.$name.' = :value WHERE '.$this->primaryKey.' = :id';
				$stmt	= $this->connection->prepare( $query );
				$stmt->bindParam( ':value', $value );
				$stmt->bindParam( ':id', $id );
				$stmt->execute();
			}
			$this->connection->commit();
		}
		catch( \Exception $e ){
			$this->connection->rollBack();
			throw $e;
		}
	}

	public function validateSetup( $allowNoPrimaryKey = FALSE )
	{
		if( $this->isValid )
			return TRUE;
		if( !$this->name )
			throw new \Exception( 'Table name is not set' );
		if( !$this->fields )
			throw new \Exception( 'Table fields are not set' );
		if( !$this->primaryKey )
			throw new \Exception( 'Table primary key is not set' );
		if( !$this->modelClass )
			throw new \Exception( 'Table model class is not set' );
		$this->isValid	= TRUE;
		return TRUE;
	}
}
?>
