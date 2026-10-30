<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Normalizes values to be bound as parameters of OSQL queries.
 *
 *	Copyright (c) 2026-2026 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2026-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */

namespace CeusMedia\Database\OSQL;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;

/**
 *	Normalizes values to be bound as parameters of OSQL queries.
 *	@category		Library
 *	@package		CeusMedia_Database_OSQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2026-2026 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
final class Value
{
	/**
	 *	Returns value as scalar or NULL, to be bound as query parameter.
	 *	Booleans become 0 or 1, date/time objects become "Y-m-d H:i:s", backed enums become their value.
	 *	@param		mixed		$value		Value to normalize
	 *	@return		string|int|float|NULL
	 *	@throws		InvalidArgumentException	if value type is not supported (eg. array, other objects)
	 */
	public static function normalize( mixed $value ): string|int|float|NULL
	{
		return match( TRUE ){
			NULL === $value, is_string( $value ), is_int( $value ), is_float( $value )	=> $value,
			is_bool( $value )								=> (int) $value,
			$value instanceof DateTimeInterface				=> $value->format( 'Y-m-d H:i:s' ),
			$value instanceof BackedEnum					=> $value->value,
			default											=> throw new InvalidArgumentException( 'Value of type "'.get_debug_type( $value ).'" is not supported' ),
		};
	}
}
