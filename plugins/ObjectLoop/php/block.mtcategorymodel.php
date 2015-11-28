<?php
function smarty_block_mtcategorymodel ( $args, $content, &$ctx, &$repeat ) {
    $args[ 'model' ] = 'Category';
    require_once( 'block.mtobjectloop.php' );
    return smarty_block_mtobjectloop ( $args, $content, $ctx, $repeat );
}
?>