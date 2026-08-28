# OSQL

[← Database](../../README.md)

Object-oriented SQL query builder (`Select`, `Condition`, `Table`, `Client`) as an
alternative to the PDO table abstraction, for building and executing queries as objects.

Having a config file like this:
```php
driver   = 'mysql';
host     = 'myHost';
port     = 'myPort';
database = 'myDatabase';
username = 'myDatabaseUser';
password = 'myDatabasePassword';
```
and assuming that you load things up like this:
```php
require_once 'vendor/autoload.php';

use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Connection;
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Table;
use CeusMedia\Database\OSQL\Query\Select;

$config	= (object) parse_ini_file( 'myConfigFile.ini' );
```
you can connect to a database like this:
```php
$client	= new Client( new Connection( DataSourceName::renderStatic(
    $config->driver,
    $config->database,
    $config->host,
    $config->port,
    $config->username,
    $config->password
), $config->username, $config->password ) );
```
Now you can query the database like this:
```php
$result	= Select::create( $client )
    ->from( new Table( 'galleries', 'g' ) )
    ->where( new Condition( 'galleryId', 1, Condition::OP_EQ ) )
    ->execute();
```
The result will contain the requested rows (only one in this example):
```php
new UI_DevOutput();
print_m( $result );
```
will produce:

```php
[O] 0 -> stdClass
   [S] galleryId => 1
   [S] status => 0
   [S] rank => 1
   [S] path => test
   [S] title => Test
   [S] description => Das ist ein Test.
   [S] timestamp => 1402008611
   ```
