# Conditions, orders and limits

## Conditions

Conditions are maps of columns and values. By default, a value is compared by equality –
a prefix in the value switches to another SQL operator:

| Condition value | Resulting SQL |
|-----------------|---------------|
| `12` / `'foo'` | `= 12` / `= 'foo'` |
| `'%foo%'`, `'foo%'` | `LIKE '%foo%'`, `LIKE 'foo%'` |
| `'% foo'` / `'!% foo'` | `LIKE 'foo'` / `NOT LIKE 'foo'` |
| `'> 10'`, `'>= 10'`, `'< 10'`, `'<= 10'`, `'!= 10'` | comparison operators |
| `'>< 1 & 10'` / `'!>< 1 & 10'` | `BETWEEN 1 AND 10` / `NOT BETWEEN 1 AND 10` |
| `'\| 4'`, `'& 4'`, `'^ 4'`, `'<< 4'`, `'>> 4'`, `'&~ 4'` | bitwise operators |
| `'is null'` / `'is not null'` | `IS NULL` / `IS NOT NULL` |
| `NULL` | `IS NULL` |
| `[1, 2, 3]` | `(… = 1 OR … = 2 OR … = 3)` – works like `IN (1, 2, 3)` |

```php
$list	= $orders->getAll( [
	'customerId'	=> [12, 13],                       // customer 12 or 13
	'total'			=> '>= 20',                         // at least 20
	'note'			=> '%gift%',                        // contains "gift"
	'createdAt'		=> '>< 2026-01-01 & 2026-12-31',    // in 2026
	'flags'			=> '& 4',                           // bit 3 set
] );
```

Several conditions are combined by `AND`. Each value of a list may use operators, too:
`['total' => ['< 10', '> 1000']]` finds very cheap *or* very expensive orders.

!!! warning "Whitespace after operators"
    The space between an operator and its value is required – `'>=20'` raises an
    `InvalidArgumentException`. Only `%` works without leading operator, by putting the wildcard
    directly into the value.

!!! info "Safe by design"
    All values are quoted by the connection. Numbers stay numbers, numeric strings stay strings
    (a zip code `'01067'` keeps its leading zero). Condition values of other types (eg. `bool` or
    objects) raise an `InvalidArgumentException`.

!!! info "Unknown columns raise an exception"
    A condition on a column which is not declared raises a `DomainException` – a typo like
    `['custmerId' => 12]` would otherwise remove a filter silently and find *all* entries.
    So do not pass request data through as conditions, but map filter names to columns yourself.

The same conditions work for `getAll()`, `count()`, `countFast()`, `getDistinct()`,
`getColumnOfAllByConditions()` and `getColumnsOfAllByConditions()`.
Index maps of `…ByIndices()` methods use the same syntax, but accept declared indices only.

## Orders

Orders are pairs of columns and directions:

```php
$orderBy	= [
	'status'	=> 'ASC',
	'createdAt'	=> 'DESC',
];
```

Unknown columns raise a `DomainException`, directions other than `ASC` and `DESC` an
`InvalidArgumentException`.

## Limits

Limits are a list of offset and number of entries:

```php
$limits	= [20, 10];     // skip 20, take 10 – the third page of ten
```

## Fields, grouping and having

`getAll()` has more parameters: a list of fields to select, columns to group by and having
conditions. Fields may be aliased and contain aggregate functions:

```php
$report	= $orders->getAll(
	['status' => [1, 2]],                                     // conditions
	[],                                                       // orders
	[],                                                       // limits
	['customerId', 'COUNT(*) AS number', 'SUM(total) AS revenue'],
	['customerId'],                                           // GROUP BY
	['COUNT(*)' => '> 5'],                                    // HAVING
);
//  [{customerId: 12, number: 8, revenue: '1234.50'}, …]
```

If only one field is given, a list of values is returned instead of a list of entries.
Prefer the [dedicated column methods](reading.md#single-values-and-columns) for that, which have a fixed return type.

| Part | Allowed |
|------|---------|
| fields | `*`, declared columns, `column AS alias`, `FUNCTION(column) AS alias`, `FUNCTION(*) AS alias` |
| groupings | declared columns, aliases of selected fields |
| having keys | `FUNCTION(column)`, `FUNCTION(*)`, declared columns, aliases of selected fields |
| having values | the [condition syntax](#conditions): operators, lists of values combined by OR |

Functions are limited to `COUNT`, `SUM`, `AVG`, `MIN` and `MAX`, aliases to letters, digits and underscores.
Everything is rebuilt and masked before it is put into the query; anything else raises a `DomainException`.
So, like conditions, fields, groupings and having are safe against SQL injection.

Having conditions referring to aliases:

```php
$report	= $orders->getAll( [], [], [], ['customerId', 'SUM(total) AS revenue'], ['customerId'], ['revenue' => '>= 1000'] );
```

!!! info "Having conditions as strings"
    For compatibility with older versions, a simple having condition can also be given as string,
    eg. `'COUNT(*) > 5'` or `"note = 'gift'"`: a key as above, an operator (`=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`)
    and a number or a quoted string. Other strings raise an `InvalidArgumentException`. Prefer the map form.
