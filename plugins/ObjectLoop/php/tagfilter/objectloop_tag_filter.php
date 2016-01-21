<?php
class ObjectLoopTagFilter
{
    public function tag_filter_mtentryassetsbycf ( $mt, &$ctx, &$args, $content = NULL ) {
        $entry = $ctx->stash( 'entry' );
        if ( $entry ) {
            $extras = $ctx->stash( 'filter_extras' );
            $extras[ 'join' ][ 'mt_objectasset' ] = array(
                'condition' => "(objectasset_object_ds = 'entry' and objectasset_object_id = "
                . $entry->id . " and asset_id = objectasset_asset_id)"
            );
            $extras = $ctx->stash( 'filter_extras', $extras );
        }
    }
}
?>