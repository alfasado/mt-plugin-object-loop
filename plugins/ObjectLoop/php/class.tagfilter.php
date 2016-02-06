<?php
class TagFilter
{
    public function tag_filter( $callback, &$mt, &$ctx, &$args, $content = NULL ) {
        $plugins_dir = $ctx->plugins_dir;
        foreach( $plugins_dir as $dir ) {
            $filter_dir = $dir . DIRECTORY_SEPARATOR . 'tagfilter';
            if ( is_dir( $filter_dir ) ) {
                $dirs = explode( DIRECTORY_SEPARATOR, $filter_dir );
                $plugin_id = $dirs[ count( $dirs ) - 3 ];
                $plugin = strtolower( $plugin_id );
                $function = $plugin . '_' . $callback;
                $require =  $filter_dir . DIRECTORY_SEPARATOR . 'class.' .$plugin . '_tag_filter.php';
                if ( file_exists( $require ) ) {
                    require_once ( $require );
                    $classname = $plugin_id . 'TagFilter';
                    $class = new $classname;
                    if ( method_exists( $class, $callback ) ) { 
                        $res = $class->$callback( $mt, $ctx, $args, $content );
                    }
                }
            }
        }
    }
}
?>