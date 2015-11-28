<?php
class GetObjectTags
{
    protected $_object_tag_cache = array();
    protected $_blog_object_tag_cache = array();
    protected $_tag_cache = array();

    public function fetch_object_tags( $ctx, $args, $blog_filter = NULL ) {
        $tags = array();
        $_datasource = $args[ '_datasource' ];
        if (! $_datasource ) {
            return $tags;
        }
        $_datasource = strtolower( $_datasource );
        $cacheable = empty( $args[ 'tags' ] )
            && empty( $args[ 'include_private' ] );
        if ( empty( $args[ 'include_private' ] ) ) {
            $private_filter = 'and (tag_is_private = 0 or tag_is_private is NULL)';
        }
        if ( isset( $args[ 'object_id' ] ) ) {
            if ( $cacheable ) {
                if (isset($this->_object_tag_cache[ $_datasource . '_' . $args[ 'object_id' ] ] ) )
                    return $this->_object_tag_cache[ $_datasource . '_' .  $args[ 'object_id' ] ];
            }
            $object_filter = 'and objecttag_object_id = '.intval($args['object_id']);
        }
        if ( $blog_filter ) {
            $blog_filter = "and objecttag_blog_id $blog_filter ";
            if ( isset( $this->_tag_cache[ $blog_filter ] ) ) {
                return $this->_tag_cache[ $blog_filter ];
            }
        } else {
            if ( isset( $args[ 'blog_id' ] ) ) {
                if ( $cacheable ) {
                    if (isset($this->_blog_object_tag_cache[ $args[ 'blog_id' ] ] ) )
                        return $this->_blog_object_tag_cache[ $args[ 'blog_id' ] ];
                }
                $blog_filter = 'and objecttag_blog_id = '.intval( $args[ 'blog_id' ] );
            }
        }
        if (! empty( $args[ 'tags' ] ) ) {
            $tag_list = '';
            require_once( 'MTUtil.php' );
            $tag_array = tag_split( $args[ 'tags' ] );
            foreach ( $tag_array as $tag ) {
                if ( $tag_list != '' ) $tag_list .= ',';
                $tag_list .= "'" . $ctx->mt->db()->escape( $tag ) . "'";
            }
            if ( $tag_list != '' ) {
                $tag_filter = 'and (tag_name in (' . $tag_list . '))';
                $private_filter = '';
            }
        }
        $sort_col = isset( $args[ 'sort_by' ] ) ? $args[ 'sort_by' ] : 'name';
        $sort_col = "tag_$sort_col";
        if ( isset( $args[ 'sort_order' ] ) and $args[ 'sort_order' ] == 'descend' ) {
            $order = 'desc';
        } else {
            $order = 'asc';
        }
        $id_order = '';
        if ( $sort_col == 'tag_name' ) {
            $sort_col = 'lower(tag_name)';
        } else {
            $id_order = ', lower(tag_name)';
        }
        $sql = "
            select tag_id, tag_name, count(*) as tag_count
            from mt_tag, mt_objecttag, mt_${_datasource}
            where objecttag_tag_id = tag_id
                and ${_datasource}_id = objecttag_object_id and objecttag_object_datasource='${_datasource}'
                $blog_filter
                $private_filter
                $tag_filter
                $object_filter
            group by tag_id, tag_name
            order by $sort_col $order $id_order
        ";
        $rs = $ctx->mt->db()->SelectLimit( $sql );
        require_once( 'class.mt_tag.php' );
        $tags = array();
        while(! $rs->EOF ) {
            $tag = new Tag;
            $tag->tag_id = $rs->Fields( 'tag_id' );
            $tag->tag_name = $rs->Fields( 'tag_name' );
            if ( isset( $object_filter ) ) {
                $tag->tag_count = '';
            } else {
                $tag->tag_count = $rs->Fields( 'tag_count' );
            }
            $tags[] = $tag;
            $rs->MoveNext();
        }
        if ( $cacheable ) {
            if ( $args[ 'object_id' ] ) {
                $this->_object_tag_cache[ $args[ 'object_id' ] ] = $tags;
            } elseif ( $args[ 'blog_id' ] ) {
                $this->_blog_object_tag_cache[ $args[ 'blog_id' ] ] = $tags;
            }
        }
        if ( $blog_filter ) {
            $this->_tag_cache[ $blog_filter ] = $tags;
        }
        return $tags;
    }
}
?>