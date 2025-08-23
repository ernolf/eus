# eus
## ernolfs utility scripts - little script flakes for linux admins

---
---

#### [**math-tools/calc**](/math-tools)
  - command-line calculator written in Python. Unlike Bash, it is not limited by integer size or precision constraints, allowing it to handle very large numbers and perform calculations with high precision, including many decimal places.

---

#### [**networking/fbwanip**](/networking/fbwanip)
  - grab WAN IP from your fritzbox.
#### [**networking/myip**](/networking/myip)
  - grab IP from host.

---

#### [**php-tools/php_dump_consts.php**](/php-tools#readme)

  - command-line utility to **list and search all PHP constants** in your PHP version, with flexible filtering options.  
Supports filtering by category, prefix, suffix, pattern, or substring search, and shows the type of each constant.


#### [**php-tools/phpscan_fix_nullable.php**](/php-tools#readme)

  - a command-line tool to **detect and optionally fix implicitly nullable typed parameters** in PHP source code.  
It searches for parameters declared with a type and a default `= null` (e.g. `function foo(Type $x = null)`), and rewrites them into an explicit nullable type.  

    You can choose between two fixing modes:  
    - **default** → adds a nullable prefix (`?Type`)  
    - **union mode** → adds a union type (`Type|null`)  

    The script also supports **dry-run**, **backup & restore (undo)**, **custom backup suffix**, and **path/extension/exclude control**.  

> [!NOTE]
> This tool is relevant for PHP **8.0 and later**, where union and nullable types are supported.  
> Starting with **PHP 8.4**, implicitly nullable parameters (e.g. `Type $x = null` without `?Type` or `Type|null`) are deprecated and will trigger warnings, which is why this tool exists — to help you modernize your codebase before future PHP versions make this a hard error.

---

#### [**time-conversion/d2u**](/time-conversion/d2u)
  - convert human readable date format to unixtime (seconds since the unix epoch = 1970-01-01 00:00 UTC)
#### [**time-conversion/u2d**](/time-conversion/u2d)
  - convert unixtime to human readable date format
#### [**time-conversion/unix2datelong**](/time-conversion/unix2datelong)
  - convert unixtime to human readable long date format
#### [**time-conversion/filetime2unix**](/time-conversion/filetime2unix)
  - convert windows filetime (a 64-bit value representing the number of 100-nanosecond intervals since 1601-01-01- 00:00 UTC) to unixtime and human readable time format
#### [**time-conversion/unix2filetime**](/time-conversion/unix2filetime)
  - convert unixtime or human readable time to windows filetime
#### [**time-conversion/today**](/time-conversion/today)
  - little calendar of actual month, today is highlighted
#### [**time-conversion/year**](/time-conversion/year)
  - little calendar of actual year

---

