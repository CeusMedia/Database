<?php
declare(strict_types=1);

/**
 *	Generates the API reference of the library as Markdown pages for MkDocs.
 *
 *	Reads all classes in src/ by reflection and their doc blocks (except excluded namespaces),
 *	writes one page per class into doc/Manual/api/, an overview page (doc/Manual/api/index.md)
 *	and replaces the API part of the navigation in util/mkdocs.yml (between marker comments).
 *	Examples written by hand in util/docs/examples/<Class path>.md are included in the class page.
 *
 *	Usage: php util/docs/generate-api.php
 */

$rootPath		= dirname( __DIR__, 2 ).'/';
$sourcePath		= $rootPath.'src/';
$targetPath		= $rootPath.'doc/Manual/api/';
$examplesPath	= __DIR__.'/examples/';
$configFile		= $rootPath.'util/mkdocs.yml';
$rootNamespace	= 'CeusMedia\\Database';
$sourceUrl		= 'https://github.com/CeusMedia/Database/blob/0.7.x/';

require_once $rootPath.'vendor/autoload.php';

/**
 *	@phpstan-type DocBlock array{summary: string, description: string, tags: array<string, list<string>>}
 */
final class ApiDocGenerator
{
	/** @var array<string,string> Tags not to be shown, since covered by reflection or meta data */
	protected const IGNORED_TAGS	= ['access', 'author', 'category', 'ceusmedia', 'codeCoverageIgnore',
		'copyright', 'license', 'link', 'noinspection', 'package', 'phpstan', 'static', 'todo', 'abstract', 'public'];

	/** @var array<string,string> Groups of navigation, keyed by namespace prefix (relative) */
	protected const GROUPS			= [
		'PDO'			=> 'PDO',
		'OSQL'			=> 'Query builder',
		'Utility'		=> 'Utilities',
		''				=> 'Core',
	];

	/** @var list<string> Namespaces (relative) not to be documented */
	protected const EXCLUDED_NAMESPACES	= [
		'DAO',
	];

	/** @var array<string,ReflectionClass<object>> */
	protected array $classes	= [];

	public function __construct(
		protected string $sourcePath,
		protected string $targetPath,
		protected string $examplesPath,
		protected string $rootNamespace,
		protected string $sourceUrl,
	)
	{
	}

	public function run( string $configFile ): void
	{
		$this->collectClasses();
		$this->clearTarget();
		foreach( $this->classes as $relativeName => $class ){
			$file	= $this->targetPath.$this->getPagePath( $relativeName );
			if( !is_dir( dirname( $file ) ) )
				mkdir( dirname( $file ), 0775, TRUE );
			file_put_contents( $file, $this->renderClass( $relativeName, $class ) );
		}
		file_put_contents( $this->targetPath.'index.md', $this->renderIndex() );
		$this->updateNavigation( $configFile );
		printf( 'Generated API reference of %d classes in %s'.PHP_EOL, count( $this->classes ), $this->targetPath );
	}

	//  --  PROTECTED  --  //

	protected function clearTarget(): void
	{
		if( is_dir( $this->targetPath ) ){
			$iterator	= new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $this->targetPath, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach( $iterator as $item )
				$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		else
			mkdir( $this->targetPath, 0775, TRUE );
	}

	protected function collectClasses(): void
	{
		$iterator	= new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->sourcePath, FilesystemIterator::SKIP_DOTS ) );
		foreach( $iterator as $file ){
			if( 'php' !== $file->getExtension() )
				continue;
			$relativeName	= str_replace( '/', '\\', substr( $file->getPathname(), strlen( $this->sourcePath ), -4 ) );
			if( in_array( explode( '\\', $relativeName )[0], self::EXCLUDED_NAMESPACES, TRUE ) )
				continue;
			$className		= $this->rootNamespace.'\\'.$relativeName;
			if( !class_exists( $className ) && !interface_exists( $className ) && !trait_exists( $className ) && !enum_exists( $className ) )
				continue;
			$this->classes[$relativeName]	= new ReflectionClass( $className );
		}
		uksort( $this->classes, static fn( string $a, string $b ): int => strcmp( strtolower( $a ), strtolower( $b ) ) );
	}

	/**
	 *	@return		string		Group label for relative class name
	 */
	protected function getGroup( string $relativeName ): string
	{
		$parts	= explode( '\\', $relativeName );
		$prefix	= count( $parts ) > 1 ? $parts[0] : '';
		return self::GROUPS[$prefix] ?? 'Other';
	}

	protected function getPagePath( string $relativeName ): string
	{
		return str_replace( '\\', '/', $relativeName ).'.md';
	}

	/**
	 *	Returns relative link from one class page to another class page (or anchor on it).
	 */
	protected function getRelativeLink( string $fromRelativeName, string $toRelativeName, string $anchor = '' ): string
	{
		$from	= explode( '/', $this->getPagePath( $fromRelativeName ) );
		$to		= explode( '/', $this->getPagePath( $toRelativeName ) );
		array_pop( $from );
		$link	= str_repeat( '../', count( $from ) ).implode( '/', $to );
		return $link.( '' !== $anchor ? '#'.$anchor : '' );
	}

	protected function getRelativeName( string $className ): ?string
	{
		$className	= ltrim( $className, '\\' );
		if( !str_starts_with( $className, $this->rootNamespace.'\\' ) )
			return NULL;
		$relativeName	= substr( $className, strlen( $this->rootNamespace ) + 1 );
		return isset( $this->classes[$relativeName] ) ? $relativeName : NULL;
	}

	/**
	 *	@return		DocBlock
	 */
	protected function parseDocBlock( string|false $docComment ): array
	{
		$result	= ['summary' => '', 'description' => '', 'tags' => []];
		if( FALSE === $docComment || '' === trim( $docComment ) )
			return $result;

		$lines	= preg_split( '/\R/', $docComment ) ?: [];
		$text	= [];
		$tag	= NULL;
		foreach( $lines as $line ){
			$line	= preg_replace( '@^\s*/\*\*@', '', $line ) ?? '';
			$line	= preg_replace( '@\*/\s*$@', '', $line ) ?? '';
			$line	= preg_replace( '@^\s*\*\t?@', '', $line ) ?? '';
			$line	= rtrim( $line );
			if( preg_match( '/^@(\w+)\s*(.*)$/s', ltrim( $line ), $matches ) ){
				$tag	= $matches[1];
				$result['tags'][$tag][]	= trim( $matches[2] );
				continue;
			}
			if( NULL !== $tag ){
				//  continuation of a tag description
				if( '' !== trim( $line ) ){
					$index	= count( $result['tags'][$tag] ) - 1;
					$result['tags'][$tag][$index]	.= ' '.trim( $line );
				}
				continue;
			}
			$text[]	= $line;
		}
		$text	= trim( implode( "\n", $text ) );
		if( '' === $text )
			return $result;

		$paragraphs	= preg_split( '/\n\s*\n/', $text, 2 ) ?: [];
		$first		= $paragraphs[0];
		//  summary is first sentence/line block, unless it is followed by a list
		$firstLines	= explode( "\n", $first );
		$summary	= [];
		while( [] !== $firstLines && !preg_match( '/^\s*[-*]\s/', $firstLines[0] ) )
			$summary[]	= trim( array_shift( $firstLines ) );
		$result['summary']		= implode( ' ', $summary );
		if( in_array( $result['summary'], ['...', '…'], TRUE ) )
			$result['summary']	= '';
		$rest					= trim( implode( "\n", $firstLines )."\n\n".( $paragraphs[1] ?? '' ) );
		$result['description']	= $this->formatDescription( $rest );
		return $result;
	}

	/**
	 *	Converts doc block text to Markdown: tab indented lists become nested lists.
	 */
	protected function formatDescription( string $text ): string
	{
		$lines		= [];
		$previous	= '';
		foreach( explode( "\n", $text ) as $line ){
			if( preg_match( '/^(\t*)\s*([-*])\s+(.*)$/', $line, $matches ) ){
				$line	= str_repeat( '    ', strlen( $matches[1] ) ).'- '.$matches[3];
				if( '' !== trim( $previous ) && !preg_match( '/^\s*- /', $previous ) )
					$lines[]	= '';
			}
			else
				$line	= trim( $line );
			$lines[]	= $line;
			$previous	= $line;
		}
		return trim( implode( "\n", $lines ) );
	}

	protected function escapeTable( string $text ): string
	{
		return str_replace( ['|', "\n"], ['\\|', ' '], $text );
	}

	/**
	 *	Renders a type for a table cell, linking classes of this library.
	 */
	protected function renderTypeLinked( ?ReflectionType $type, string $currentRelativeName ): string
	{
		if( NULL === $type )
			return '<span class="api-muted">mixed</span>';
		$parts	= preg_split( '/([|&?()])/', (string) $type, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];
		$out		= [];
		$nullable	= '';
		foreach( $parts as $part ){
			if( '?' === $part ){
				$nullable	= '?';
				continue;
			}
			if( in_array( $part, ['|', '&', '(', ')'], TRUE ) ){
				$out[]	= '|' === $part ? ' \\| ' : $part;
				continue;
			}
			if( in_array( $part, ['self', 'static'], TRUE ) )
				$part	= $this->rootNamespace.'\\'.$currentRelativeName;
			$relativeName	= $this->getRelativeName( $part );
			if( NULL !== $relativeName ){
				$short	= $this->shortName( $relativeName );
				$out[]	= $relativeName === $currentRelativeName
					? '`'.$nullable.$short.'`'
					: '[`'.$nullable.$short.'`]('.$this->getRelativeLink( $currentRelativeName, $relativeName ).')';
			}
			else
				$out[]	= '`'.$nullable.$this->shortName( $part ).'`';
			$nullable	= '';
		}
		return implode( '', $out );
	}

	protected function renderTypePlain( ?ReflectionType $type ): string
	{
		if( NULL === $type )
			return '';
		$parts	= preg_split( '/([|&?()])/', (string) $type, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];
		return implode( '', array_map( fn( string $part ): string => $this->shortName( $part ), $parts ) );
	}

	protected function shortName( string $className ): string
	{
		$position	= strrpos( $className, '\\' );
		return FALSE === $position ? $className : substr( $className, $position + 1 );
	}

	protected function renderValue( mixed $value ): string
	{
		return match( TRUE ){
			NULL === $value		=> 'NULL',
			TRUE === $value		=> 'TRUE',
			FALSE === $value	=> 'FALSE',
			is_string( $value )	=> "'".addcslashes( $value, "'\\\n\r\t" )."'",
			is_array( $value )	=> $this->renderArray( $value ),
			is_object( $value )	=> 'new '.$this->shortName( $value::class ).'()',
			default				=> var_export( $value, TRUE ),
		};
	}

	/**
	 *	@param		array<int|string,mixed>		$value
	 */
	protected function renderArray( array $value ): string
	{
		if( [] === $value )
			return '[]';
		$items	= [];
		foreach( $value as $key => $item )
			$items[]	= ( array_is_list( $value ) ? '' : $this->renderValue( $key ).' => ' ).$this->renderValue( $item );
		$string	= '['.implode( ', ', $items ).']';
		return strlen( $string ) > 80 ? '[…]' : $string;
	}

	protected function renderDefault( ReflectionParameter $parameter ): string
	{
		if( !$parameter->isDefaultValueAvailable() )
			return '';
		if( $parameter->isDefaultValueConstant() ){
			$constant	= (string) $parameter->getDefaultValueConstantName();
			return str_starts_with( $constant, $this->rootNamespace.'\\' ) ? $this->shortName( $constant ) : $constant;
		}
		return $this->renderValue( $parameter->getDefaultValue() );
	}

	protected function renderSignature( ReflectionMethod $method ): string
	{
		$modifiers	= implode( ' ', Reflection::getModifierNames( $method->getModifiers() ) );
		$parameters	= [];
		foreach( $method->getParameters() as $parameter ){
			$string	= trim( $this->renderTypePlain( $parameter->getType() ).' '
				.( $parameter->isPassedByReference() ? '&' : '' )
				.( $parameter->isVariadic() ? '...' : '' )
				.'$'.$parameter->getName() );
			if( $parameter->isDefaultValueAvailable() )
				$string	.= ' = '.$this->renderDefault( $parameter );
			$parameters[]	= $string;
		}
		$return		= $method->hasReturnType() ? ': '.$this->renderTypePlain( $method->getReturnType() ) : '';
		$line		= $modifiers.' function '.$method->getName().'( '.implode( ', ', $parameters ).' )'.$return;
		if( strlen( $line ) > 100 && count( $parameters ) > 1 )
			$line	= $modifiers.' function '.$method->getName()."(\n    ".implode( ",\n    ", $parameters )."\n)".$return;
		return str_replace( '(  )', '()', $line );
	}

	/**
	 *	@param		ReflectionClass<object>		$class
	 */
	protected function renderClass( string $relativeName, ReflectionClass $class ): string
	{
		$doc	= $this->parseDocBlock( $class->getDocComment() );
		$kind	= match( TRUE ){
			$class->isInterface()	=> 'interface',
			$class->isTrait()		=> 'trait',
			$class->isEnum()		=> 'enum',
			$class->isAbstract()	=> 'abstract class',
			$class->isFinal()		=> 'final class',
			default					=> 'class',
		};

		$md		= [];
		$md[]	= '---';
		$md[]	= 'title: '.$relativeName;
		$md[]	= 'description: '.json_encode( '' !== $doc['summary'] ? $doc['summary'] : $class->getName(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$md[]	= '---';
		$md[]	= '';
		$md[]	= '# '.$this->shortName( $relativeName ).' <small class="api-kind">'.$kind.'</small>';
		$md[]	= '';
		$md[]	= '<div class="api-fqcn"><code>'.$class->getName().'</code></div>';
		$md[]	= '';

		$meta	= [];
		$parent	= $class->getParentClass();
		if( FALSE !== $parent )
			$meta[]	= '**Extends** '.$this->renderClassReference( $parent->getName(), $relativeName );
		$interfaces	= $class->getInterfaceNames();
		if( FALSE !== $parent )
			$interfaces	= array_diff( $interfaces, $parent->getInterfaceNames() );
		if( [] !== $interfaces )
			$meta[]	= '**Implements** '.implode( ', ', array_map( fn( string $name ): string => $this->renderClassReference( $name, $relativeName ), $interfaces ) );
		$children	= [];
		foreach( $this->classes as $otherName => $other )
			if( FALSE !== $other->getParentClass() && $other->getParentClass()->getName() === $class->getName() )
				$children[]	= $this->renderClassReference( $other->getName(), $relativeName );
		if( [] !== $children )
			$meta[]	= '**Extended by** '.implode( ', ', $children );
		$sourceFile	= 'src/'.str_replace( '\\', '/', $relativeName ).'.php';
		$meta[]	= '**Source** [`'.$sourceFile.'`]('.$this->sourceUrl.$sourceFile.')';
		$md[]	= implode( ' &nbsp;·&nbsp; ', $meta );
		$md[]	= '';

		if( '' !== $doc['summary'] ){
			$md[]	= $doc['summary'];
			$md[]	= '';
		}
		if( '' !== $doc['description'] ){
			$md[]	= $doc['description'];
			$md[]	= '';
		}
		foreach( $doc['tags']['deprecated'] ?? [] as $deprecated )
			array_push( $md, '!!! warning "Deprecated"', '    '.( '' !== $deprecated ? $deprecated : 'This class is deprecated.' ), '' );
		foreach( $doc['tags']['see'] ?? [] as $see )
			array_push( $md, '**See** '.$see, '' );

		$exampleFile	= $this->examplesPath.$this->getPagePath( $relativeName );
		if( file_exists( $exampleFile ) ){
			$md[]	= '## Examples';
			$md[]	= '';
			$md[]	= trim( (string) file_get_contents( $exampleFile ) );
			$md[]	= '';
		}

		//  constants
		$constants	= array_filter(
			$class->getReflectionConstants( ReflectionClassConstant::IS_PUBLIC ),
			static fn( ReflectionClassConstant $constant ): bool => $constant->getDeclaringClass()->getName() === $class->getName()
		);
		if( [] !== $constants ){
			$md[]	= '## Constants';
			$md[]	= '';
			$md[]	= '| Name | Value | Description |';
			$md[]	= '|------|-------|-------------|';
			foreach( $constants as $constant ){
				$constantDoc	= $this->parseDocBlock( $constant->getDocComment() );
				$description	= $constantDoc['summary'];
				if( '' === $description && isset( $constantDoc['tags']['var'][0] ) ){
					$description	= preg_replace( '/^\S+\s*/', '', $constantDoc['tags']['var'][0] ) ?? '';
					$description	= preg_replace( '/^'.$constant->getName().'\s*/', '', $description ) ?? '';
				}
				$md[]	= sprintf( '| `%s` | `%s` | %s |',
					$constant->getName(),
					$this->escapeTable( $this->renderValue( $constant->getValue() ) ),
					$this->escapeTable( $description )
				);
			}
			$md[]	= '';
		}

		//  public properties
		$properties	= array_filter(
			$class->getProperties( ReflectionProperty::IS_PUBLIC ),
			static fn( ReflectionProperty $property ): bool => $property->getDeclaringClass()->getName() === $class->getName()
		);
		if( [] !== $properties ){
			$md[]	= '## Properties';
			$md[]	= '';
			$md[]	= '| Name | Type | Description |';
			$md[]	= '|------|------|-------------|';
			foreach( $properties as $property ){
				$propertyDoc	= $this->parseDocBlock( $property->getDocComment() );
				$description	= $propertyDoc['summary'];
				if( '' === $description && isset( $propertyDoc['tags']['var'][0] ) )
					$description	= preg_replace( '/^\S+\s*(\$\S+\s*)?/', '', $propertyDoc['tags']['var'][0] ) ?? '';
				$md[]	= sprintf( '| `%s$%s` | %s | %s |',
					$property->isStatic() ? 'static ' : '',
					$property->getName(),
					$this->renderTypeLinked( $property->getType(), $relativeName ),
					$this->escapeTable( $description )
				);
			}
			$md[]	= '';
		}

		//  methods
		$methods	= array_filter(
			$class->getMethods( ReflectionMethod::IS_PUBLIC ),
			static fn( ReflectionMethod $method ): bool => $method->getDeclaringClass()->getName() === $class->getName()
		);
		usort( $methods, static function( ReflectionMethod $a, ReflectionMethod $b ): int {
			$rank	= static fn( ReflectionMethod $method ): int => '__construct' === $method->getName() ? 0 : 1;
			return [$rank( $a ), strtolower( $a->getName() )] <=> [$rank( $b ), strtolower( $b->getName() )];
		} );

		if( [] !== $methods ){
			$md[]	= '## Methods';
			$md[]	= '';
			$md[]	= '| Method | Description |';
			$md[]	= '|--------|-------------|';
			foreach( $methods as $method ){
				$methodDoc	= $this->parseDocBlock( $method->getDocComment() );
				$badges		= ( $method->isStatic() ? ' <span class="api-badge api-badge--static">static</span>' : '' )
					.( isset( $methodDoc['tags']['deprecated'] ) ? ' <span class="api-badge api-badge--deprecated">deprecated</span>' : '' );
				$md[]	= sprintf( '| [`%s()`](#%s)%s | %s |',
					$method->getName(),
					strtolower( $method->getName() ),
					$badges,
					$this->escapeTable( $methodDoc['summary'] )
				);
			}
			$md[]	= '';
			foreach( $methods as $method )
				array_push( $md, ...$this->renderMethod( $relativeName, $method ) );
		}

		//  inherited methods
		if( FALSE !== $parent ){
			$inherited	= [];
			foreach( $class->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ){
				$declaring	= $method->getDeclaringClass()->getName();
				if( $declaring === $class->getName() )
					continue;
				$declaringRelative	= $this->getRelativeName( $declaring );
				$inherited[$declaring][]	= NULL !== $declaringRelative
					? '[`'.$method->getName().'()`]('.$this->getRelativeLink( $relativeName, $declaringRelative, strtolower( $method->getName() ) ).')'
					: '`'.$method->getName().'()`';
			}
			if( [] !== $inherited ){
				$md[]	= '## Inherited methods';
				$md[]	= '';
				foreach( $inherited as $declaring => $links ){
					$md[]	= '??? info "From '.$this->shortName( $declaring ).'"';
					$md[]	= '    '.implode( ', ', $links );
					$md[]	= '';
				}
			}
		}
		return implode( "\n", $md )."\n";
	}

	protected function renderClassReference( string $className, string $currentRelativeName ): string
	{
		$relativeName	= $this->getRelativeName( $className );
		if( NULL === $relativeName )
			return '`'.ltrim( $className, '\\' ).'`';
		return '[`'.$relativeName.'`]('.$this->getRelativeLink( $currentRelativeName, $relativeName ).')';
	}

	/**
	 *	@return		list<string>
	 */
	protected function renderMethod( string $relativeName, ReflectionMethod $method ): array
	{
		$doc	= $this->parseDocBlock( $method->getDocComment() );
		$md		= [];
		$badges	= [];
		if( $method->isStatic() )
			$badges[]	= '<span class="api-badge api-badge--static">static</span>';
		if( $method->isAbstract() )
			$badges[]	= '<span class="api-badge">abstract</span>';
		if( $method->isFinal() )
			$badges[]	= '<span class="api-badge">final</span>';
		if( isset( $doc['tags']['deprecated'] ) )
			$badges[]	= '<span class="api-badge api-badge--deprecated">deprecated</span>';

		$md[]	= '### '.$method->getName().'() { #'.strtolower( $method->getName() ).' .api-method data-toc-label="'.$method->getName().'()" }';
		$md[]	= '';
		if( [] !== $badges ){
			$md[]	= implode( ' ', $badges );
			$md[]	= '';
		}
		$md[]	= '```php';
		$md[]	= $this->renderSignature( $method );
		$md[]	= '```';
		$md[]	= '';
		foreach( $doc['tags']['deprecated'] ?? [] as $deprecated )
			array_push( $md, '!!! warning "Deprecated"', '    '.( '' !== $deprecated ? $deprecated : 'This method is deprecated.' ), '' );
		if( '' !== $doc['summary'] ){
			$md[]	= $doc['summary'];
			$md[]	= '';
		}
		if( '' !== $doc['description'] ){
			$md[]	= $doc['description'];
			$md[]	= '';
		}

		//  parameters, described by doc block
		$paramDocs	= [];
		foreach( $doc['tags']['param'] ?? [] as $param )
			if( preg_match( '/^(\S+)?\s*&?(?:\.\.\.)?\$(\w+)\s*(.*)$/s', $param, $matches ) )
				$paramDocs[$matches[2]]	= ['type' => $matches[1], 'description' => trim( $matches[3] )];
		if( [] !== $method->getParameters() ){
			$md[]	= '| Parameter | Type | Default | Description |';
			$md[]	= '|-----------|------|---------|-------------|';
			foreach( $method->getParameters() as $parameter ){
				$default	= $this->renderDefault( $parameter );
				$md[]	= sprintf( '| `$%s` | %s | %s | %s |',
					$parameter->getName(),
					$parameter->hasType()
						? $this->renderTypeLinked( $parameter->getType(), $relativeName )
						: '`'.( $paramDocs[$parameter->getName()]['type'] ?? 'mixed' ).'`',
					'' !== $default ? '`'.$this->escapeTable( $default ).'`' : '<span class="api-muted">required</span>',
					$this->escapeTable( $paramDocs[$parameter->getName()]['description'] ?? '' )
				);
			}
			$md[]	= '';
		}

		//  return value
		$returnDoc	= $doc['tags']['return'][0] ?? '';
		$returnText	= trim( preg_replace( '/^\S+\s*/', '', $returnDoc ) ?? '' );
		if( $method->hasReturnType() && 'void' !== (string) $method->getReturnType() && '__construct' !== $method->getName() ){
			$md[]	= '**Returns** '.$this->renderTypeLinked( $method->getReturnType(), $relativeName )
				.( '' !== $returnText ? ' — '.$returnText : '' );
			$md[]	= '';
		}

		//  exceptions
		$throws	= $doc['tags']['throws'] ?? [];
		if( [] !== $throws ){
			$md[]	= '**Throws**';
			$md[]	= '';
			foreach( $throws as $throw ){
				$parts	= preg_split( '/\s+/', $throw, 2 ) ?: [''];
				$md[]	= '- `'.ltrim( $parts[0], '\\' ).'`'.( isset( $parts[1] ) && '' !== $parts[1] ? ' — '.$parts[1] : '' );
			}
			$md[]	= '';
		}

		foreach( $doc['tags']['see'] ?? [] as $see )
			array_push( $md, '**See** '.$see, '' );
		foreach( $doc['tags']['example'] ?? [] as $example )
			array_push( $md, '**Example** `'.$example.'`', '' );
		return $md;
	}

	protected function renderIndex(): string
	{
		$md		= [];
		$md[]	= '---';
		$md[]	= 'title: API Reference';
		$md[]	= '---';
		$md[]	= '';
		$md[]	= '# API Reference';
		$md[]	= '';
		$md[]	= 'Generated from the source code and its doc blocks: all classes of namespace `'.$this->rootNamespace.'`, their public constants, properties and methods.';
		$md[]	= '';
		$md[]	= '!!! tip "Where to start"';
		$md[]	= '    Most applications only need a [`PDO\Connection`](PDO/Connection.md) '
			.'(configured by a [`PDO\DataSourceName`](PDO/DataSourceName.md)) and own table classes extending [`PDO\Table`](PDO/Table.md). '
			.'Entity classes may extend [`PDO\Entity`](PDO/Entity.md). '
			.'For joins and aggregates, the [`OSQL\Client`](OSQL/Client.md) builds queries.';
		$md[]	= '';
		$groups	= [];
		foreach( $this->classes as $relativeName => $class )
			$groups[$this->getGroup( $relativeName )][$relativeName]	= $class;
		foreach( $this->getOrderedGroups( $groups ) as $group => $classes ){
			$md[]	= '## '.$group;
			$md[]	= '';
			$md[]	= '| Class | Description |';
			$md[]	= '|-------|-------------|';
			foreach( $classes as $relativeName => $class ){
				$doc	= $this->parseDocBlock( $class->getDocComment() );
				$md[]	= sprintf( '| [`%s`](%s) | %s |', $relativeName, $this->getPagePath( $relativeName ), $this->escapeTable( $doc['summary'] ) );
			}
			$md[]	= '';
		}
		return implode( "\n", $md );
	}

	/**
	 *	@template T
	 *	@param		array<string,T>		$groups
	 *	@return		array<string,T>
	 */
	protected function getOrderedGroups( array $groups ): array
	{
		$ordered	= [];
		foreach( array_merge( array_values( self::GROUPS ), ['Other'] ) as $label )
			if( isset( $groups[$label] ) )
				$ordered[$label]	= $groups[$label];
		return $ordered;
	}

	/**
	 *	Replaces navigation between marker comments in MkDocs config.
	 */
	protected function updateNavigation( string $configFile ): void
	{
		$config	= (string) file_get_contents( $configFile );
		$start	= '# api-nav:start';
		$end	= '# api-nav:end';
		if( !preg_match( '/^([ \t]*)'.preg_quote( $start, '/' ).'.*?^[ \t]*'.preg_quote( $end, '/' ).'/ms', $config, $matches ) ){
			fwrite( STDERR, 'Markers for API navigation not found in '.$configFile.PHP_EOL );
			return;
		}
		$indent	= $matches[1];
		$lines	= [$indent.$start, $indent.'- api/index.md'];
		$groups	= [];
		foreach( $this->classes as $relativeName => $class )
			$groups[$this->getGroup( $relativeName )][]	= $relativeName;
		foreach( $this->getOrderedGroups( $groups ) as $group => $relativeNames ){
			$lines[]	= $indent.'- '.$group.':';
			foreach( $relativeNames as $relativeName ){
				$label		= $relativeName;
				$parts		= explode( '\\', $relativeName );
				if( count( $parts ) > 1 )
					$label	= implode( '\\', array_slice( $parts, 1 ) );
				$lines[]	= $indent.'  - '.json_encode( $label, JSON_UNESCAPED_SLASHES ).': api/'.$this->getPagePath( $relativeName );
			}
		}
		$lines[]	= $indent.$end;
		$config		= str_replace( $matches[0], implode( "\n", $lines ), $config );
		file_put_contents( $configFile, $config );
	}
}

( new ApiDocGenerator( $sourcePath, $targetPath, $examplesPath, $rootNamespace, $sourceUrl ) )->run( $configFile );
