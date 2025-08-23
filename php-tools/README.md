## **php_dump_consts.php**

  - command-line utility to list and search all PHP constants in your PHP version, with flexible filtering options.  
    Supports filtering by category, prefix, suffix, pattern, or substring search, and shows the type of each constant.

  - **Usage** 👇
    <details>
       <summary> 👉 🔽 👈</summary>

    ```bash
     ./php_dump_consts.php [OPTIONS] [CONSTNAME]
    ```

    **Options:**

    `--all`

    - Show all constants grouped by category

    &nbsp;

    `--category=NAME`

    - Show constants only from given category (case insensitive)

    &nbsp;

    `--prefix=STR`

    - Show constants starting with STR

    &nbsp;

    `--suffix=STR`

    - Show constants ending with STR

    &nbsp;

    `--pattern=REGEX`

    - Show constants matching regex (PCRE)

    &nbsp;

    `--search=SUBSTR`

    - Show constants containing SUBSTR anywhere

    &nbsp;

    `--help`

    - Show this help

    &nbsp;

    **Notes:**
      - If invoked without options, a summary of categories with counts is displayed.
      - Use --all to display all constants in all categories.
      - If CONSTNAME is given without option, its value is displayed if it exists,
        otherwise a hint is printed.
      - Constants are grouped by category (core, pcre, standard, etc.).
      - Each constant shows its type: [int], [string], [bool], [array], [float], [null].

    &nbsp;

    **Examples:**
      ```bash
       ./php_dump_consts.php                    # List categories with counts
       ./php_dump_consts.php --category=core    # Show constants in 'core'
       ./php_dump_consts.php --all              # Show all constants
       ./php_dump_consts.php --prefix=E_        # Show all error constants
       ./php_dump_consts.php PHP_EOL            # Show value of constant PHP_EOL
       ./php_dump_consts.php --suffix=_ERROR    # All constants ending with _ERROR
       ./php_dump_consts.php --pattern='/^STD/' # Regex match
       ./php_dump_consts.php --search=HTTP      # All constants containing 'HTTP'
      ```
    </details>

---
## **phpscan_fix_nullable.php**

  - command-line tool to **detect and optionally fix implicitly nullable typed parameters** in PHP source code.  
It searches for parameters declared with a type and a default `= null` (e.g. `function foo(Type $x = null)`), and rewrites them into an explicit nullable type.  

> [!NOTE]
> This tool is relevant for PHP **8.0 and later**, where union and nullable types are supported.  
> Starting with **PHP 8.4**, implicitly nullable parameters (e.g. `Type $x = null` without `?Type` or `Type|null`) are deprecated and will trigger warnings, which is why this tool exists — to help you modernize your codebase before future PHP versions make this a hard error.

  - **Usage** 👇
     <details>
       <summary> 👉 🔽 👈</summary>

       ```bash
        ./phpscan_fix_nullable.php --path=/path/to/project [options]
       ```

       **Options:**

       `--path=DIR`
       - Directory to scan (recursively).  
         Default: `.` (current working directory).  
         Must be a readable directory.

    &nbsp;

       `--list`
       - List all occurrences of implicitly nullable parameters, without modifying files.

    &nbsp;

       `--dry-run`

       - Show which changes would be applied, but do not write anything to disk.  
         Useful to preview changes before --apply.

    &nbsp;

       `--apply`

       - Apply the fixes in place.  
         A backup of each modified file is created using the configured suffix (default: `.phpscan_fix_nullable.bak`).

    &nbsp;

       `--exclude=PAT1,PAT2,...`

       - Comma-separated list of path fragments to exclude from scanning.  
         Matching is substring-based.  
         Example:

         ```php
          --exclude=vendor,apps
         ```

    &nbsp;

       `--extensions=EXT1,EXT2,...`

       - Comma-separated list of file extensions to scan.  
         Default: `php`  
         Example:

         ```php
          --extensions=php,inc,phpt
         ```

    &nbsp;

       `--union`

       - Instead of `?Type`, use `Type|null` when fixing.  
         Example:

         ```php
          // Before
          function f(Type $x = null)

          // After (without --union)
          function f(?Type $x = null)

          // After (with --union)
          function f(Type|null $x = null)
         ```

    &nbsp;

       `--restore` / `--undo`

       - Restore all backups in the given --path by renaming them back to the original file name.  
         Only affects files ending with the chosen backup suffix.

    &nbsp;

       `--suffix=SUFFIX`

       - Set the backup suffix to use when applying fixes and when restoring.  
         Default: .phpscan_fix_nullable.bak  
         Example:

         ```php
          --suffix=.bak
         ```

    &nbsp;

       `--help`

       - Show usage information and exit.

    &nbsp;

       **Typical Workflows**


       - Scan only (no changes):

         ```sh
          ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --list
         ```

    &nbsp;

       - Preview fixes:

         ```sh
          ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --dry-run
         ```

    &nbsp;

       - Apply fixes with backups:

         ```sh
          ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --apply
         ```

    &nbsp;

       - Apply fixes with union types and custom suffix:

         ```sh
          ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --apply --union --suffix=.bak
         ```

    &nbsp;

       - Undo/restore all changes:

         ```sh
          ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --restore
         ```

    &nbsp;

       **Safety:**

       - Backups: Every modified file is backed up before changes, using the configured suffix.
       - Undo: You can always revert with --restore/--undo.
       - Dry-run: Preview changes without risk.
       - Excludes: Avoid scanning unwanted directories like vendor, test, 3rdparty or apps.

    </details>

---
