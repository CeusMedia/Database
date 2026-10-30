# Utilities

## MySQL export and import

[`Utility\MySQL\Export`](../api/Utility/MySQL/Export.md) and [`Utility\MySQL\Import`](../api/Utility/MySQL/Import.md)
dump and load a MySQL database by the command line clients `mysqldump` and `mysql`, which must be installed.
They are meant for command line scripts, eg. for backups, deployments or test fixtures, and report progress on the console.

```php
use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\Utility\MySQL\Export;
use CeusMedia\Database\Utility\MySQL\Import;

$dsn	= DataSourceName::getInstance( 'mysql', 'shop' )
	->setHost( 'localhost' )
	->setUsername( 'myUser' )
	->setPassword( 'myPassword' );

$result	= ( new Export( $dsn ) )->exportToFile( 'backup.sql' );
$result	= ( new Import( $dsn ) )->importFile( 'backup.sql' );

if( 0 !== $result->code )
	echo implode( PHP_EOL, $result->output );
```

Database, username and password must be set in the DSN. Both methods return an object with the exit
`code` and the `output` lines of the command.

Credentials are not given on the command line, where other users of the system could see them in the
process list: they are written into a temporary MySQL options file instead. `setUseTempOptionsFile( FALSE )`
disables that.

### Progress messages

Both classes print progress messages, like found tables and file sizes – on the console, or as
output in a web context. `setVerbose( FALSE )` silences them, eg. for cron jobs, web requests or tests:

```php
$result	= ( new Export( $dsn ) )->setVerbose( FALSE )->exportToFile( 'backup.sql' );
```

The output of the command line client itself is not printed anyway, but returned in `$result->output`.

### Selected tables

```php
$export->exportToFile( 'shop.sql', 'shop_' );                       // only tables with prefix "shop_"
$export->exportToFile( 'shop.sql', 'shop_', ['shop_sessions'] );    // … but without sessions
```

### Portable dumps with table prefix placeholders

A dump can replace the table prefix by a placeholder, so it can be imported with another prefix –
eg. to copy an installation, or to load fixtures for a test prefix:

```php
$export->exportToFileWithPrefix( 'shop.sql', 'shop_' );   // `shop_orders` becomes `<%?prefix%>orders`
$import->importFileWithPrefix( 'shop.sql', 'test_' );     // `<%?prefix%>orders` becomes `test_orders`
```

The placeholder can be changed on both sides by `setPrefixPlaceholder()`.

## SQLSTATE meanings

[`SQLSTATE`](../api/SQLSTATE.md) explains SQLSTATE codes, eg. of a caught
[SQL exception](connection.md#error-handling):

```php
use CeusMedia\Database\SQLSTATE;

echo SQLSTATE::getMeaning( '23000' );   // "Integrity constraint violation - Integrity constraint violation"
echo SQLSTATE::getMeaning( '42S02' );   // "Syntax error or access rule violation - base table or view not found"
```
