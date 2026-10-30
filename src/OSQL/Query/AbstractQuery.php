<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Abstract OSQL query.
 *
 *	Copyright (c) 2010-2026 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\OSQL\Query;

use CeusMedia\Database\OSQL\Client;

/**
 *	Abstract OSQL query.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL_Query
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2010-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
abstract class AbstractQuery implements QueryInterface
{
	/** @var string|NULL $statement Statement of latest execution, with placeholders */
	public ?string $statement		= NULL;

	/** @var array<int,string|int|float|NULL> $parameters Parameters of latest execution */
	public array $parameters		= [];

	protected Client $client;

	/**
	 *	Constructor.
	 *	@param		Client		$client		OSQL client to execute query with
	 */
	public function __construct( Client $client )
	{
		$this->client	= $client;
	}

	/**
	 *	@return		object{query: string, parameters: array<int,string|int|float|NULL>}
	 */
	abstract public function render(): object;

	/**
	 *	@param		string								$query
	 *	@param		array<int,string|int|float|NULL>	$parameters
	 *	@return		object{query: string, parameters: array<int,string|int|float|NULL>}
	 */
	protected static function result( string $query, array $parameters ): object
	{
		return (object) ['query' => $query, 'parameters' => $parameters];
	}
}
