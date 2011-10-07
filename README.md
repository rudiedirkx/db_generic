Very simple, very flawed DBAL for

(from awesomeness to assness)

* SQlite 3
* MySQLi
* MySQL
* Sqlite 2

Yes, SQLite 3 to the max!

Used in (amongst many, many more (private) projects) https://github.com/rudiedirkx/series

## TODO

* Implement replaceholders (fixed num_args) with `?` in `update`, `select`, `count` etc
* Replace MySQL with MySQLi and SQLite with SQLite3 (and PDO only)
* Enforce UTF-8 on connect, always (encoding, charset, names whatever it's called in SQLite)
* All arrays: all conditions, updates, etc
* Cool conditions and updates: array('a' => 'b', 'x > 2') like in ROW
