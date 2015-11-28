<?php
class TagFilter
{
    public function tag_filter( $callback, &$mt, &$ctx, &$args, $content = NULL ) {
        $plugins_dir = $ctx->plugins_dir;
        foreach( $plugins_dir as $dir ) {
            $filter_dir = $dir . DIRECTORY_SEPARATOR . 'tagfilter';
            if ( is_dir( $filter_dir ) ) {
                $dirs = explode( DIRECTORY_SEPARATOR, $filter_dir );
                $plugin = strtolower( $dirs[ count( $dirs ) - 3 ] );
                $function = $plugin . '_' . $callback;
                $require = $filter_dir . DIRECTORY_SEPARATOR . $function . '.php';
                if ( file_exists( $require ) ) {
                    require_once $require;
                    $res = $function( $mt, $ctx, $args, $content );
                }
            }
        }
    }
}
?>