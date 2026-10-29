<?php
/**
 * RouterOS write safety switch.
 *
 * Router identity checks and other read-only commands remain available while
 * this is false. Enable writes only after confirming that the router and
 * subscriber network are safe to modify.
 */
if (!defined('FLEXIHUB_ROUTEROS_WRITE_ENABLED')) {
    define('FLEXIHUB_ROUTEROS_WRITE_ENABLED', false);
}
