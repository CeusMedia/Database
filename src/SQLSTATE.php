<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 *	Get meaning of SQLSTATE code.
 *
 *	Copyright (c) 2007-2024 Christian Würker (ceusmedia.de)
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
 *	@package		CeusMedia_Database
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 *	@see			https://www.microfocus.com/documentation/visual-cobol/vc60/VS2017/HRDBRHESQL16.html
 */

namespace CeusMedia\Database;

use CeusMedia\Common\Exception\Conversion as ConversionException;
use CeusMedia\Common\Exception\IO as IoException;
use CeusMedia\Common\XML\Element;
use CeusMedia\Common\XML\ElementReader as XmlElementReader;

/**
 *	Get meaning of SQLSTATE code.
 *
 *	@category		Library
 *	@package		CeusMedia_Database
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2007-2024 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Database
 */
class SQLSTATE
{
	/** @var Element|NULL $xml */
	protected static ?Element $xml		= NULL;

	/**
	 *	Resolves SQLSTATE Code and returns its Meaning.
	 *	Returns 'unknown', if reading or parsing of SQLSTATE.xml failed
	 *	@static
	 *	@param		string		$SQLSTATE		SQLSTATE code
	 *	@return		string|NULL
	 *	@see		http://developer.mimer.com/documentation/html_92/Mimer_SQL_Mobile_DocSet/App_Return_Codes2.html
	 *	@see		http://publib.boulder.ibm.com/infocenter/idshelp/v10/index.jsp?topic=/com.ibm.sqls.doc/sqls520.htm
	 */
	public static function getMeaning( string $SQLSTATE ): ?string
	{
		try{
			if( NULL === self::$xml )
				self::$xml	= XmlElementReader::readFile( __DIR__.'/SQLSTATE.xml' );
			return self::getMeaningFromXml( self::$xml, $SQLSTATE );
		}
		catch( IoException|ConversionException $e ){
			return 'unknown';
		}
	}

	/**
	 *	@param		Element		$root
	 *	@param		string		$SQLSTATE
	 *	@return		string|NULL
	 */
	protected static function getMeaningFromXml( Element $root, string $SQLSTATE ): ?string
	{
		$class1	= substr( $SQLSTATE, 0, 2 );
		$class2	= substr( $SQLSTATE, 2, 3 );
		$query	= 'class[@id="'.$class1.'"]/subclass[@id="000"]';
		/** @var array $result */
		$result	= $root->xpath( $query ) ?? [];
		$class	= array_pop( $result );
		if( $class ){
			$query		= 'class[@id="'.$class1.'"]/subclass[@id="'.$class2.'"]';
			/** @var array $result */
			$result		= $root->xpath( $query ) ?? [];
			$subclass	= array_pop( $result );
			if( $subclass )
				return $class->getAttribute( 'meaning' ).' - '.$subclass->getAttribute( 'meaning' );
			return $class->getAttribute( 'meaning' );
		}
		return NULL;
	}
}