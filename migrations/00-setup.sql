PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA journal_size_limit = 67108864; -- 64 megabytes
PRAGMA mmap_size = 134217728; -- 128 megabytes
PRAGMA temp_store = MEMORY;
PRAGMA cache_size = 2000;
PRAGMA busy_timeout = 5000;
PRAGMA foreign_keys = ON;
