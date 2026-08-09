<?php
/**
 * What the WP_CLI stand-in throws where the real facade would exit.
 *
 * Its own file because the coding standard allows one class per file and that
 * rule is not relaxed for the suite, and loaded from bootstrap.php ahead of
 * helper-wp-cli.php, which throws it.
 *
 * @package WP-Sweep
 */

/**
 * Raised by the WP_CLI stand-in's error() in place of exiting the process.
 */
class WP_Sweep_CLI_Halt extends Exception {}
