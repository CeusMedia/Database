## Version 0.6.2
- General:
	- Import SQLSTATE from CeusMedia::Common.
- PDO:
	- Support data and entity objects.
	- Add connection factory.
	- Use entity class in cli demo.
- OSQL:
	- Update to reach PHPStan level max + strict.

## Version 0.6.1

- Add new LIKE support in condition query.
- Add entity support on fetching.
- Split PDO connection into several class files.
- Unfinalize PDO connection constructor and disable static constructor.
- Code Style: Short Array Syntax.
- Provide unit test config file as dist.
- Reach max PHPStan level.
- Update OSQL.
- Update copyright year.
- Add composer lock file.
- Add badges.

## Version 0.6.0
- Use PHP 8.1.
- Drop support for PHP 7.4. See branch 0.5.x.
- Set PHPStan to PHP 8.1.
- Use PHPUnit 10.1.
- Fix bugs.

## Version 0.5.1
- Support CeusMedia/Common 1.0.x.
- Short array syntax.
- Allow array of values for PDO:::Table::*ByIndex.

## Version 0.5.0
- Fully typed (class members and method arguments).
- Reach PHPStan level 9 (max!) PLUS extra strict rules (for PDO).
- Add composer scripts.
- Short array syntax.
- Constants visibility.
- Refactored, updated unit tests.

## Version 0.4.4
- General: Support PHP 8.

## Version 0.4.3
- PDO: Improve code quality.

## Version 0.4.2
- General:
	- Update PHPUnit to version 9 and migrate configuration.
	- Include PHPStan.
	- Include PHP-CS-Fixer.
- PDO:
	- Apply PHPStan and improve code strictness.
	- Add connection pool.

## Version 0.4.1
- General:
	- Relax needed version of CeusMedia/Common.
	- Improve error handling in CLI demo.
- PDO
	- Support functions and operations as fields.
	- Allow functions as operations as result fields.
	- Extend table reader by method to list distinct column values.
	- Add static constructors.

## Version 0.4.0
- General:
	- Remove cache from composer file.
	- Include PHPUnit and update tests.
	- Set method return types and adjust tests.
	- Migrate to PHP 7.2.
	- Update composer file to require atleast PHP 7.2.
	- Change EOL from CRLF to LF end remove closing PHP tags.
	- Update copyright year in code doc.
	- Move trailing line comments to inline comments.
	- Update make file to use PHPUnit from vendor folder.
	- Extend README by short OSQL manual.
	- Update test code syntax.
- OSQL
	- Refactor abstract query and query interface.
	- Query building:
		- Add condition group for structured AND and OR conditions.
		- Extend select by orders.
		- Extend select by left join.
		- Improve join handling.
		- Add condition operation constants.
	- Query Execution:
		- Save rendered query parts as object.
		- Save bound statement parameters.
		- Save resulting information of query execution.
	- Fix bug.
	- Update code style.
	- Add browser demo.
	- Add CLI demo.
- PDO
	- Support bitwise operators in table reader.
	- Migrate to PHP 7.2.
	- Set method return types and adjust tests.
	- Update  browser demo.
	- Add  CLI demo.
- DAO
	- Update code syntax.

## Version 0.3.3
- General:
	- Set copyright year in code doc.
	- Update make and doc files.
	- Improve API doc generation.
- PDO
	- Extend get methods by unused underlaying features.
	- Improve namespace and code doc.
	- Fix bug in error handling.
	- Extend connection to list tables.
	- Improve exceptions.
	- Improve tests.

## Version 0.3.2
- General: Update copyright year.
- OSQL: Refactor query interface and abstract class.
- PDO: Fix bug.

## Version 0.3.1
- General: Improve readme and extend by first instructions.
- PDO: Improve table by changes in model of CeusMedia/HydrogenFramework.
- PDO: Improve editByIndices by parameter to strip HTML tags or not.

## Version 0.3.0
- General:
	- Updated composer file.
	- Update versions in composer file.
	- Update license in composer file.
	- Add make file.
	- Add XML file for PHPUnit.
	- Add config file for DocCreator.
- PDO
	- Cast return value of count methods to integer.
	- Fix bug in remove.
	- Add browser demo prototype.
	- Add unit tests.
	- Update classes by changes in CeusMedia/Common.
	- Improve column enumeration in table reader and writer.
- OSQL:
	- Updated classes.
