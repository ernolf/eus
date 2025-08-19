# **phpscan_fix_nullable.php**

  - command-line tool to **detect and optionally fix implicitly nullable typed parameters** in PHP source code.  
It searches for parameters declared with a type and a default `= null` (e.g. `function foo(Type $x = null)`), and rewrites them into an explicit nullable type.  

    > **Note:** This tool is relevant for PHP **8.0 and later**, where union and nullable types are supported.  
    > Starting with **PHP 8.4**, implicitly nullable parameters (e.g. `Type $x = null` without `?Type` or `Type|null`) are deprecated and will trigger warnings, which is why this tool exists — to help you modernize your codebase before future PHP versions make this a hard error.


    ### Usage

    ```bash
     ./phpscan_fix_nullable.php --path=/path/to/project [options]
    ```

    Options:

    `--path=DIR`

    - Directory to scan (recursively).

      Default: `.` (current working directory).

      Must be a readable directory.

    `--list`

    - List all occurrences of implicitly nullable parameters, without modifying files.

    `--dry-run`

    - Show which changes would be applied, but do not write anything to disk.

      Useful to preview changes before --apply.

    `--apply`

    - Apply the fixes in place.

      A backup of each modified file is created using the configured suffix (default: `.phpscan_fix_nullable.bak`).

    `--exclude=PAT1,PAT2,...`

    - Comma-separated list of path fragments to exclude from scanning.

      Matching is substring-based.

      Example:

    ```php
     --exclude=vendor,apps
    ```

    `--extensions=EXT1,EXT2,...`

    - Comma-separated list of file extensions to scan.

      Default: `php`

      Example:

    ```php
     --extensions=php,inc,phpt
    ```

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

    `--restore` / `--undo`

    - Restore all backups in the given --path by renaming them back to the original file name.

      Only affects files ending with the chosen backup suffix.

    `--suffix=SUFFIX`

    - Set the backup suffix to use when applying fixes and when restoring.

      Default: .phpscan_fix_nullable.bak

      Example:

    ```php
     --suffix=.bak
    ```

    `--help`

    - Show usage information and exit.


    ### Typical Workflows

    - Scan only (no changes):

    ```sh
     ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --list
    ```

    - Preview fixes:

    ```sh
     ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --dry-run
    ```

    - Apply fixes with backups:

    ```sh
     ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --apply
    ```

    - Apply fixes with union types and custom suffix:

    ```sh
     ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --apply --union --suffix=.bak
    ```

    - Undo/restore all changes:

    ```sh
     ./phpscan_fix_nullable.php --path=/var/www/nextcloud/apps --restore
    ```

    - Safety

      - Backups: Every modified file is backed up before changes, using the configured suffix.

      - Undo: You can always revert with --restore/--undo.

      - Dry-run: Preview changes without risk.

      - Excludes: Avoid scanning unwanted directories like vendor, test, 3rdparty or apps.

