-- https://dev.to/briandouglasie/sensible-sqlite-defaults-5ei7

PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA journal_size_limit = 67108864; -- 64 megabytes
PRAGMA mmap_size = 134217728; -- 128 megabytes
PRAGMA temp_store = MEMORY;
PRAGMA cache_size = -20000; -- 20MiB https://www.sqlite.org/pragma.html#pragma_cache_size
PRAGMA busy_timeout = 5000;
PRAGMA foreign_keys = ON;
PRAGMA auto_vacuum = INCREMENTAL;
