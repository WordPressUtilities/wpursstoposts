<?php
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit();

require_once dirname( __FILE__ ).'/wpursstoposts.php';

$wpursstoposts->uninstall();
