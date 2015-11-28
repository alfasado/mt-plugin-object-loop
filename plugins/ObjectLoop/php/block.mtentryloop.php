<?php
function smarty_block_mtentryloop ( $args, $content, &$ctx, &$repeat ) {
    $args[ 'model' ] = 'Entry';
    require_once( 'block.mtobjectloop.php' );
    return smarty_block_mtobjectloop ( $args, $content, $ctx, $repeat );
}
?>