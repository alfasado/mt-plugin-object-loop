<?php
require_once( 'MTUtil.php' );
function smarty_block_mtobjectloop ( $args, $content, &$ctx, &$repeat ) {
    $_classname = $args[ 'model' ];
    require_once( 'class.mt_' . strtolower( $_classname ) . '.php' );
    $_classname = ucfirst( $_classname );
    $_object = new $_classname;
    $_datasource = $_object->_table;
    $_datasource = str_replace( 'mt_', '', $_datasource );
    $deniedobjects = $ctx->mt->config( 'DeniedLoopObjects' );
    if (! is_string( $deniedobjects ) ) {
        $deniedobjects = 'permission,config,log,session';
    }
    $serialized = $ctx->mt->config( 'SerializedColumns' );
    if (! is_string( $serialized ) ) {
        $serialized = 'author_list_prefs,author_widgets';
    }
    if ( $serialized ) {
        $serialized = preg_split( '/\s*,\s*/', $serialized );
    }
    if ( $deniedobjects ) {
        $deniedobjects = preg_split( '/\s*,\s*/', $deniedobjects );
        if ( in_array( $_datasource, $deniedobjects ) ) {
            $repeat = FALSE;
            return '';
        }
    }
    $this_tag = strtolower( $ctx->this_tag() );
    $customfields = array();
    foreach ( $args as $key => $value ) {
        if ( strpos( $key, 'field___' ) === 0 ) {
            $custom_field_value = $args[ $key ];
            $key = str_replace( 'field___', '', $key );
            $customfields[ $key ] = $custom_field_value;
        }
    }
    $meta_fields = array();
    $meta_info = $_object->get_meta_info( $_datasource );
    foreach ( $meta_info as $meta => $_type ) {
        $meta = str_replace( 'field.', $_datasource . '_', $meta );
        $meta_fields[] = $meta;
    }
    $attr_names = $_object->GetAttributeNames();
    array_push( $attr_names, $_datasource . '_tags' );
    $localvars = array( $attr_names, common_loop_vars(),
        array( "_${_datasource}_counter" ), $meta_fields );
    if (! isset( $content ) ) {
        $ctx->localize( $localvars );
        $ctx->stash( $_datasource . '_old_vars', $old_vars );
        if ( isset( $args[ 'sort_by' ] ) ) {
            $sort_by = $args[ 'sort_by' ];
            unset( $args[ 'sort_by' ] );
        }
        if ( isset( $args[ 'sort_order' ] ) ) {
            $sort_order = $args[ 'sort_order' ];
            unset( $args[ 'sort_order' ] );
        }
        if ( isset( $args[ 'limit' ] ) ) {
            $limit = $args[ 'limit' ];
            unset( $args[ 'limit' ] );
        }
        if ( isset( $args[ 'offset' ] ) ) {
            $offset = $args[ 'offset' ];
            unset( $args[ 'offset' ] );
        }
        if (! $sort_order ) {
            $sort_order = 'ascend';
        }
        $where = '';
        $_blog_id;
        $field = array();
        foreach ( $args as $key => $value ) {
            if ( ( $key != '_object' ) && ( $key != '_datasource' ) ) {
                if ( $_object->has_column( $key ) ) {
                    if ( $key == 'class' ) {
                        if ( $value == '*' ) {
                            continue;
                        }
                    }
                    if ( $where ) $where .= " AND ";
                    $value = $ctx->mt->db()->escape( $value );
                    if ( $key == 'blog_id' ) $_blog_id = $value;
                    $where .= " ${_datasource}_$key='${value}' ";
                }
            }
        }
        if ( $_object->has_column( 'class' ) ) {
            if (! isset( $args[ 'class' ] ) ) {
                $class = strtolower( $_classname );
                $class = $ctx->mt->db()->escape( $class );
                if ( $where ) $where .= " AND ";
                $where .= " ${_datasource}_class='${class}' ";
            }
        }
        $blog_filter;
        if ( $_blog_id ) {
            if ( $where ) $where .= " AND ";
            $where .= " ${_datasource}_blog_id = ${_blog_id} ";
            $blog_filter = "=${_blog_id}";
        } else {
            if ( $_datasource != 'blog' ) {
                if ( $_object->has_column( 'blog_id' ) ) {
                    $include_blogs = $ctx->mt->db()->include_exclude_blogs( $args );
                    $blog_filter = $include_blogs;
                    if ( $include_blogs ) {
                        if ( $where ) $where .= " AND ";
                        $where .= " ${_datasource}_blog_id ${include_blogs} ";
                    }
                }
            }
        }
        if ( isset( $args[ 'tags' ] ) or isset( $args[ 'tag' ] ) ) {
            $tag_arg = isset( $args[ 'tag' ] ) ? $args[ 'tag' ] : $args[ 'tags' ];
            $_args = array();
            $_args[ 'tags' ] = $tag_arg;
            $_args[ '_datasource' ] = $_datasource;
            require_once( 'class.getobjecttags.php' );
            $get_object_tags = new GetObjectTags;
            if ( $tags = $get_object_tags->fetch_object_tags( $ctx, $_args, $blog_filter ) ) {
                if ( isset( $args[ 'include_blogs' ] ) or isset( $args[ 'exclude_blogs' ] ) ) {
                    $blog_ctx_arg = isset( $args[ 'include_blogs' ] ) ?
                        array( 'include_blogs' => $args[ 'include_blogs' ] ) :
                        array( 'exclude_blogs' => $args[ 'exclude_blogs' ] );
                    if ( isset( $args[ 'include_blogs' ] ) && isset( $args[ 'include_with_website' ] ) ) {
                        $blog_ctx_arg = array_merge( $blog_ctx_arg, array( 'include_with_website' => $args[ 'include_with_website' ] ) );
                    }
                }
                $tag_list = array();
                foreach ( $tags as $tag ) {
                    $tag_list[] = $tag->tag_id;
                }
                $objecttag = $ctx->mt->db()->fetch_objecttags( array_merge( $blog_ctx_arg,
                array( 'tag_id' => $tag_list, 'datasource' => $_datasource ) ) );
                $object_list = array();
                if ( $objecttag ) {
                    foreach ( $objecttag as $ot ) {
                        $object_list[ $ot->objecttag_object_id ] = 1;
                    }
                }
                if ( count( $object_list ) ) {
                    $object_list = implode( ',', array_keys( $object_list ) );
                    if ( $where ) $where .= " AND ";
                    $where .= " ${_datasource}_id in (${object_list}) ";
                }
            }
        }
        if (! $where ) {
            $where = '1=1';
        }
        if ( $sort_by ) {
            $sort_by = $ctx->mt->db()->escape( $sort_by );
            $where .= " order by ${_datasource}_${sort_by}";
            if ( $sort_order == 'ascend' ) {
                $where .= " ASC ";
            } else {
                $where .= " DESC ";
            }
        }
        $extras = array();
        if ( $offset ) {
            $extras[ 'offset' ] = $offset;
        }
        if ( $limit ) {
            $extras[ 'limit' ] = $limit;
        }
        if ( count( $customfields ) ) {
            $meta_join_num = 1;
            if (! empty( $meta_info ) ) {
                foreach ( $customfields as $name => $value ) {
                    if ( isset( $meta_info[ 'field.' . $name ] ) ) {
                        $meta_col = $meta_info[ 'field.' . $name ];
                        $value = $ctx->mt->db()->escape( $value );
                        $table = "mt_${_datasource}_meta ${_datasource}_meta${meta_join_num}";
                        $extras[ 'join' ][ $table ] = array(
                            'condition' => "(${_datasource}_meta${meta_join_num}.${_datasource}_meta_${_datasource}_id = ${_datasource}_id
                                and ${_datasource}_meta$meta_join_num.${_datasource}_meta_type = 'field.$name'
                                and ${_datasource}_meta$meta_join_num.${_datasource}_meta_$meta_col='${value}')\n"
                        );
                        $meta_join_num++;
                    }
                }
            }
        }
        if (! isset( $args[ 'no_filter' ] ) ) {
            $ctx->stash( 'filter_where', $where );
            $ctx->stash( 'filter_extras', $extras );
            $ctx->stash( 'filter_args', $args );
            $ctx->stash( 'filter_this_tag', $this_tag );
            require_once( 'class.tagfilter.php' );
            $filter = new TagFilter;
            $filter->tag_filter( 'tag_filter_' . $this_tag, $ctx->mt, $ctx, $args );
            $where = $ctx->stash( 'filter_where' );
            $extras = $ctx->stash( 'filter_extras' );
            $args = $ctx->stash( 'filter_args' );
        }
        $objects = $_object->Find( $where, FALSE, FALSE, $extras );
        $counter = 0;
        $ctx->stash( $_datasource . '_multi', $objects );
    } else {
        $objects = $ctx->stash( $_datasource . '_multi' );
        $counter = $ctx->stash( "_${_datasource}_counter" );
    }
    if (! count( $objects ) ) {
        $ctx->restore( $localvars );
        $repeat = FALSE;
        return '';
    }
    $lastn = count( $objects );
    $methods = array();
    $paths = array();
    if ( $_datasource == 'blog' ) {
        $methods = array( 'blog_site_path', 'blog_site_url',
        'blog_archive_path', 'blog_archive_url' );
    } else if ( $_datasource == 'asset' ) {
        $paths = array( 'asset_url', 'asset_file_path' );
        $blog = $ctx->stash( 'blog' );
        $site_url = $blog->site_url();
        $site_url = preg_replace( '/\/$/', '', $site_url );
        require_once( 'function.mtstaticwebpath.php' );
        $static_url = smarty_function_mtstaticwebpath( $args, $ctx );
        $archive_url = $blog->archive_url();
        if ( $archive_url ) {
            $archive_url = preg_replace( '/\/$/', '', $archive_url );
        }
        $site_path = $blog->site_path();
        $site_path = preg_replace( '/\/$/', '', $site_path );
    }
    if ( $counter < $lastn ) {
        $data = $objects[ $counter ];
        $values = $data->GetArray();
        if ( $data->has_meta() ) {
            $meta = $data->get_meta_info()[ $_datasource ];
            if ( is_array( $meta ) ) {
                foreach ( $meta as $key => $value ) {
                    $field_name = $_datasource . '_' . str_replace( '.', '_', $key );
                    $values[ $field_name ] = $data->$key;
                }
            }
        }
        if (! isset( $args[ 'no_tags' ] ) ) {
            $_args = array();
            $_args[ 'object_id' ] = $data->id;
            $_args[ '_datasource' ] = $_datasource;
            require_once( 'class.getobjecttags.php' );
            $get_object_tags = new GetObjectTags;
            if ( $tags = $get_object_tags->fetch_object_tags( $ctx, $_args ) ) {
                $_tags = array();
                foreach( $tags as $_t ) {
                    $_tags[] = $_t->tag_name;
                }
                $ctx->__stash[ 'vars' ][ $_datasource . '_tags' ] = $_tags;
            } else {
                unset( $ctx->__stash[ 'vars' ][ $_datasource . '_tags' ] );
            }
        }
        foreach ( $values as $key => $value ) {
            if (! preg_match( '/password|secret/i', $key ) ) {
                if ( in_array( $key, $methods ) ) {
                    $_key = preg_replace( '/.*?_/', '', $key );
                    $value = $data->$_key();
                } else if ( in_array( $key, $paths ) ) {
                    $value = preg_replace( '/^%s\//', $static_url, $value );
                    if ( $key == 'asset_file_path' ) {
                        $value = preg_replace( '/^%r/', $site_path, $value );
                    } else {
                        $value = preg_replace( '/^%r/', $site_url, $value );
                        if ( $archive_url ) {
                            $value = preg_replace( '/^%a/', $archive_url, $value );
                        }
                    }
                }
                if ( in_array( $key, $serialized ) ) {
                    $value = preg_replace( "/^BIN:/", '', $value );
                    $value = $ctx->mt->db()->unserialize( $value );
                }
                $ctx->__stash[ 'vars' ][ $key ] = $value;
            }
        }
        $ctx->stash( $_datasource, $data );
        $ctx->stash( "_${_datasource}_counter", $counter + 1 );
        $count = $counter + 1;
        $ctx->__stash[ 'vars' ][ '__counter__' ] = $count;
        $ctx->__stash[ 'vars' ][ '__odd__' ]     = ( $count % 2 ) == 1;
        $ctx->__stash[ 'vars' ][ '__even__' ]    = ( $count % 2 ) == 0;
        $ctx->__stash[ 'vars' ][ '__first__' ]   = $count == 1;
        $ctx->__stash[ 'vars' ][ '__last__' ]    = ( $count == $lastn );
        $repeat = TRUE;
    } else {
        $ctx->restore( $localvars );
        $ctx->stash( $_datasource, NULL );
        $repeat = FALSE;
    }
    return $content;
}
?>