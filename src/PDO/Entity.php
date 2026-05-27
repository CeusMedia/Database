<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Builder for Data Source Name Strings.
 *
 *	Copyright (c) 2007-2026 Christian Würker (ceusmedia.de)
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
 *	along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *	@category		Library
 *	@package		CeusMedia_Database_PDO
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\PDO;

use ArrayAccess;
use CeusMedia\Common\ADT\Collection\Dictionary;
use Countable;
use Iterator;
use JsonSerializable;

/**
 * Builder for Data Source Name Strings.
 *
 * Copyright (c) 2007-2026 Christian Würker (ceusmedia.de)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @category        Library
 * @package        CeusMedia_Database_PDO
 * @author            Christian Würker <christian.wuerker@ceusmedia.de>
 * @copyright        2007-2026 Christian Würker
 * @license        https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 * @link            https://github.com/CeusMedia/Database
 * @implements ArrayAccess<?string,int|float|string|bool|array|object|NULL>
 * @implements Iterator<?string,int|float|string|bool|array|object|NULL>
 */

class Entity implements ArrayAccess, Countable, Iterator, JsonSerializable
{
	/**	@var		int				$_iteratorPosition		Iterator Position */
	private int $_iteratorPosition	= 0;

	/**
	 *	@param		array<string,int|float|string|array|object|NULL>		$data
	 *	@return		static
	 */
	public static function fromArray( array $data ): static
	{
		$className = static::class;
		return new $className( $data );
	}

	/**
	 *	@param		Dictionary		$dictionary
	 *	@return		static
	 */
	public static function fromDictionary( Dictionary $dictionary ): static
	{
		$className = static::class;
		return new $className( $dictionary->getAll() );
	}

	/**
	 *	Constructor.
	 *	@param		Dictionary|array<string,int|float|string|array|object|NULL>		$data
	 */
	public function __construct( Dictionary|array $data = [] )
	{
		/**
		 * @var string $key
		 * @var int|float|string|bool|NULL $value
		 */
		foreach( $data as $key => $value )
			$this->set( $key, $value );
	}

	/**
	 *	@return		int<0,max>
	 */
	public function count(): int
	{
		$properties	= get_object_vars( $this );
		unset( $properties['_iteratorPosition'] );
		return count( $properties );
	}

	/**
	 *	@param		string		$key
	 *	@return		int|float|string|bool|array|object|NULL
	 */
	public function get( string $key ): int|float|string|bool|array|object|NULL
	{
		if( $this->offsetExists( $key ) )
			/** @var int|float|string|bool|NULL $key */
			/** @phpstan-ignore-next-line */
			return $this->$key ?? null;
		return NULL;
	}

	/**
	 *	Return list of object member keys.
	 *	@access		public
	 *	@return		array<string>		List of object member keys
	 */
	public function getKeys(): array
	{
		$properties	= get_object_vars( $this );
		unset( $properties['_iteratorPosition'] );
		return array_keys( $properties );
	}

	/**
	 *	@param		string		$key
	 *	@return		bool
	 */
	public function has( string $key ): bool
	{
		/** @phpstan-ignore-next-line */
		return property_exists( $this, $key ) && NULL !== $this->$key;
	}

	/**
	 *	@param		string									$key
	 *	@param		int|float|string|bool|array|object|NULL	$value
	 *	@return		self
	 */
	public function set( string $key, int|float|string|bool|array|object|NULL $value ): self
	{
		if( property_exists( $this, $key ) )
			/** @phpstan-ignore-next-line */
			$this->$key	= $value;
		return $this;
	}

	/**
	 *	@return		array<string,int|float|string|bool|array|object|NULL>
	 */
	public function toArray(): array
	{
		$list	= [];
		/**
		 *	@var		string	$key
		 *	@var		int|float|string|bool|array|object|NULL		$value
		 */
		foreach( get_object_vars( $this ) as $key => $value )
			if( '_iteratorPosition' !== $key )
				$list[$key]	= $value;
		return $list;
	}

	/**
	 *	@return		Dictionary
	 */
	public function toDictionary(): Dictionary
	{
		return new Dictionary( $this->toArray() );
	}

	/**
	 *	@return		int|float|string|bool|array|object|NULL
	 */
	public function current(): int|float|string|bool|array|object|NULL
	{
		if( !$this->valid() )
			return NULL;
		return $this->get( $this->getKeys()[$this->_iteratorPosition] );
	}

	/**
	 *	@return		void
	 */
	public function next(): void
	{
		$this->_iteratorPosition++;
	}

	/**
	 *	@return		?string
	 */
	public function key(): ?string
	{
		if( !$this->valid() )
			return NULL;
		return $this->getKeys()[$this->_iteratorPosition];
	}

	/**
	 *	@return		bool
	 */
	public function valid(): bool
	{
		return $this->_iteratorPosition < $this->count();
	}

	/**
	 *	@return		void
	 */
	public function rewind(): void
	{
		$this->_iteratorPosition	= 0;
	}

	/**
	 *	@param		mixed	$offset
	 *	@return		bool
	 */
	public function offsetExists( mixed $offset ): bool
	{
		/** @var string $offset */
		return property_exists( $this, $offset );
	}

	/**
	 *	@param		mixed		$offset
	 *	@return		int|float|string|bool|array|object|NULL
	 */
	public function offsetGet( mixed $offset ): int|float|string|bool|array|object|NULL
	{
		/** @var string $offset */
		return $this->get( $offset );
	}

	/**
	 *	@param		mixed		$offset
	 *	@param		mixed		$value
	 *	@return		void
	 */
	public function offsetSet( mixed $offset, mixed $value ): void
	{
		/** @var string $offset */
		/** @var int|float|string|bool|NULL $value */
		$this->set( $offset, $value );
	}

	/**
	 *	@param		mixed		$offset
	 *	@return		void
	 */
	public function offsetUnset( mixed $offset ): void
	{
		/** @var string $offset */
		if( $this->offsetExists( $offset ) )
			/** @phpstan-ignore-next-line */
			unset( $this->$offset );
	}

	/**
	 *	@return		string|bool
	 */
	public function jsonSerialize(): string|bool
	{
		return json_encode( $this->toArray() );
	}
}