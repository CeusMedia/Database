<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */
declare(strict_types=1);

/**
 *	...
 *
 *	(Imported from CeusMedia::Hymn)
 *
 *	Copyright (c) 2014-2025 Christian Würker (ceusmedia.de)
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
 *	@category		Tool
 *	@package		CeusMedia_Database_Utility_MySQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2014-2025 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Hymn
 */
namespace CeusMedia\Database\Utility\MySQL;

use CeusMedia\Common\Alg\UnitFormater;
use CeusMedia\Common\CLI as Cli;
//use CeusMedia\Common\CLI\Output as CliOutput;
use CeusMedia\Common\Exception\Data\Missing as DataMissingException;
use CeusMedia\Common\Exception\IO as IoException;
use CeusMedia\Common\FS\File;
use CeusMedia\Database\PDO\Connection as PdoConnection;
use CeusMedia\Database\PDO\DataSourceName as PdoDataSourceName;
use CeusMedia\Database\Utility\TempFile;

/**
 *	...
 *
 *	(Imported from CeusMedia::Hymn)
 *
 *	@category		Tool
 *	@package        CeusMedia_Database_Utility_MySQL
 *	@author			Christian Würker <christian.wuerker@ceusmedia.de>
 *	@copyright		2014-2025 Christian Würker
 *	@license		https://www.gnu.org/licenses/gpl-3.0.txt GPL 3
 *	@link			https://github.com/CeusMedia/Hymn
 *	@todo			code documentation
 */
class Export
{
	protected PdoConnection $dbc;
	protected PdoDataSourceName $dsn;
	protected OptionsFile $optionsFile;
	protected string $prefixPlaceholder		= '<%?prefix%>';
	protected bool $useTempOptionsFile		= TRUE;
//	protected CliOutput $output;

	public function __construct( PdoDataSourceName $dsn )
	{
		self::validateDsn( $dsn );

		$this->dsn	= $dsn;
		$this->dbc	= new PdoConnection( $dsn->render(), $dsn->getUsername(), $dsn->getPassword() );

		$this->optionsFile	= new OptionsFile( $dsn );
//		$this->output		= new CliOutput();
	}

	protected static function validateDsn( PdoDataSourceName $dsn ): void
	{
		if( NULL === $dsn->getDatabase() )
			throw new DataMissingException( 'No database set' );
		if( NULL === $dsn->getUsername() )
			throw new DataMissingException( 'No username set' );
		if( NULL === $dsn->getPassword() )
			throw new DataMissingException( 'No password set' );
	}

	public function exportToFile( string $fileName, string $tablePrefix = '', array $tablesToSkip = [] ): object
	{
		$optionsFile	= NULL;
		$tables			= [];																		//  no table selection by default
		$usePrefix		= strlen( trim( $tablePrefix ) ) !== 0;
		$useSkip		= count( $tablesToSkip ) !== 0;
		$foundTables	= $this->dbc->getTables( $tablePrefix );
		if( $usePrefix || $useSkip )																//  table prefix has been set or there are tables to skip
			foreach( $foundTables as $table )														//  iterate found tables with prefix
				if( !in_array( $table, $tablesToSkip, TRUE ) )								//  table shall not be skipped
					$tables[]	= escapeshellarg( $table );											//  collect table as escaped shell arg
		if( $this->useTempOptionsFile ){
			$optionsFile	= new OptionsFile( $this->dsn );
			$tempFile		= new TempFile( $optionsFile->getDefaultFileName() );
			$tempFilePath	= $tempFile->create()->getFilePath();
			$optionsFile->create( $tempFilePath, FALSE );
			$line	= vsprintf( '%s %s %s', [												//  @see https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_compact
				join( ' ', [
					'--defaults-extra-file='.escapeshellarg( $tempFilePath ),						//  configured host as escaped shell arg
					'--result-file='.escapeshellarg( $fileName ),									//  target file
					'--skip-extended-insert',														//  each row in one insert line
				] ),
				/** @phpstan-ignore-next-line */
				escapeshellarg( $this->dsn->getDatabase() ),										//  configured database name as escaped shell arg
				join( ' ', $tables )
			] );
		}
		else {
			$line	= vsprintf( '%s %s %s', [											//  @see https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_compact
				join( ' ', [
					'--host='.escapeshellarg( $this->dsn->getHost() ?? 'localhost' ),		//  configured host as escaped shell arg
					'--port='.escapeshellarg( (string) ( $this->dsn->getPort() ?? 3306 ) ),		//  configured port as escaped shell arg
					/** @phpstan-ignore-next-line */
					'--user='.escapeshellarg( $this->dsn->getUsername() ),						//  configured username as escaped shell arg
					/** @phpstan-ignore-next-line */
					'--password='.escapeshellarg( $this->dsn->getPassword() ),					//  configured password as escaped shell arg
					'--result-file='.escapeshellarg( $fileName ),
				] ),
				/** @phpstan-ignore-next-line */
				escapeshellarg( $this->dsn->getDatabase() ),									//  configured database name as escaped shell arg
				join( ' ', $tables ),													//  collected found tables
			] );
		}
		Cli::out( 'Found '.count( $foundTables ).' table(s):' );
		foreach( $foundTables as $table )
			Cli::out( '- '.$table );
		$result	 = $this->execCommandLine( $line, 'mysqldump' );

		$size	= UnitFormater::formatBytes( File::new( $fileName )->getSize() );
		Cli::out( 'Wrote '.$fileName.' ('.$size.').' );
		if( $this->useTempOptionsFile && NULL !== $optionsFile )
			$optionsFile->remove();
		return $result;
	}

	public function exportToFileWithPrefix( string $fileName, string $prefix, array $tablesToSkip = [] ): object
	{
		$result	= $this->exportToFile( $fileName, $prefix, $tablesToSkip );
		$this->insertPrefixInFile( $fileName, $prefix );
		return $result;
	}

	public function insertPrefixInFile( string $fileName, string $prefix ): void
	{
		$quotedPrefix	= preg_quote( $prefix, '@' );
		$regExp		= "@(EXISTS|FROM|INTO|TABLE|TABLES|for table)( `)(".$quotedPrefix.")(.+)(`)@U";	//  build regular expression
		$callback	= [$this, '_callbackReplacePrefix'];											//  create replace callback

		rename( $fileName, $fileName.'_' );														//  move dump file to source file
		$fpIn		= fopen( $fileName.'_', "r" );									//  open source file
		$fpOut		= fopen( $fileName, "a" );												//  prepare empty target file
		if( FALSE === $fpIn )
			throw IoException::create( 'Failed to open read stream' )
				->setResource( $fileName.'_' );
		if( FALSE === $fpOut )
			throw IoException::create( 'Failed to open write stream' )
				->setResource( $fileName );

		while( !feof( $fpIn ) ){																	//  read input file until end
			$line	= fgets( $fpIn );																//  read line buffer
			if( FALSE === $line )
				throw IoException::create( 'Reading from stream failed' )
					->setResource( $fileName.'_' );
			/** @var string $line */
			$line	= preg_replace_callback( $regExp, $callback, $line );							//  perform replace in buffer
//			$buffer	= fread( $fpIn, 4096 );															//  read 4K buffer
//			$buffer	= preg_replace_callback( $regExp, $callback, $buffer );							//  perform replace in buffer
			if( FALSE === fwrite( $fpOut, $line ) )													//  write buffer to target file
				throw IoException::create( 'Writing to stream failed' )
					->setResource( $fileName );
		}
		fclose( $fpOut );																			//  close target file
		fclose( $fpIn );																			//  close source file
		unlink( $fileName.'_' );															//  remove source file
	}

	public function setPrefixPlaceholder( string $prefixPlaceholder ): self
	{
		$this->prefixPlaceholder	= $prefixPlaceholder;
		return $this;
	}

	public function setUseTempOptionsFile( bool $use ): self
	{
		$this->useTempOptionsFile	= $use;
		return $this;
	}

	/*  --  PROTECTED  --  */

	protected function _callbackReplacePrefix( array $matches ): string
	{
		if( $matches[1] === 'for table' )
			return $matches[1].$matches[2].$matches[4].$matches[5];
		return $matches[1].$matches[2].$this->prefixPlaceholder.$matches[4].$matches[5];
	}

	protected function execCommandLine( string $line, string $command = 'mysql' ): object
	{
		$resultCode		= 0;
		$resultOutput	= [];
		exec( escapeshellarg( $command ).' '.$line, $resultOutput, $resultCode );
		return (object) ['code' => $resultCode, 'output' => $resultOutput];
	}
}
