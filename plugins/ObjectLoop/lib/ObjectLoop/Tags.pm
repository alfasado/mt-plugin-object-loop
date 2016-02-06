package ObjectLoop::Tags;

use strict;
use warnings;

sub _hdlr_object_loop {
    my ( $ctx, $args, $cond ) = @_;
    my $tag = $args->{ model };
    if (! $tag ) {
        $tag = lc( $ctx->stash( 'tag' ) );
        $tag =~ s/loop$//;
        $tag =~ s/^model//;
        $tag =~ s/model$//;
    }
    $tag = lc( $tag );
    my $deniedobjects = MT->config( 'DeniedLoopObjects' );
    if (! defined( $deniedobjects ) ) {
        $deniedobjects = 'permission,config,log,session';
    }
    if ( $deniedobjects ) {
        my @_objects = split( /\s*,\s*/, $deniedobjects );
        if ( grep( /^$tag$/, @_objects ) ) {
            return '';
        }
    }
    my $model = MT->model( $tag );
    my $params = {};
    my $_blog_id;
    my %fields;
    my $op = $args->{ operator } || '';
    for my $arg ( keys %$args ) {
        if ( $model->has_column( $arg ) ) {
            my $value = $args->{ $arg };
            if ( $op ) {
                $params->{ $arg } = { $op => $value };
            } else {
                $params->{ $arg } = $value;
            }
            if ( $arg eq 'blog_id' ) {
                $_blog_id = $args->{ $arg };
            }
        }
        if ( $arg =~ m/^field:(.+)$/ ) {
            $fields{ $1 } = $args->{ $arg };
        }
    }
    my $extras = {};
    if ( my $sort_by = $args->{ sort_by } ) {
        $extras->{ sort } = $sort_by;
        if ( my $sort_order = $args->{ sort_order } ) {
            $extras->{ direction } = $sort_order;
        } else {
            $extras->{ direction } = 'ascend';
        }
    }
    if ( my $limit = $args->{ limit } ) {
        $extras->{ limit } = $limit;
    }
    if ( my $offset = $args->{ offset } ) {
        $extras->{ offset } = $offset;
    }
    my $column_names = $model->column_names;
    my $_blog_ids;
    my ( %blog_terms, %blog_args );
    if ( $model->has_column( 'blog_id' ) ) {
        $ctx->set_blog_load_context( $args, \%blog_terms, \%blog_args )
            or return $ctx->error( $ctx->errstr );
        $_blog_ids = $blog_terms{ blog_id };
        if ( $_blog_ids ) {
            $params->{ blog_id } = $_blog_ids;
        }
    }
    if ( my $tag_arg = $args->{ tags } || $args->{ tag } ) {
        require MT::Tag;
        require MT::ObjectTag;
        my $terms;
        my @tags = MT::Tag->split( ',', $tag_arg );
        $terms = { name => \@tags };
        my $tags = [
            MT::Tag->load(
                $terms,
                {   ( $terms ? ( binary => { name => 1 } ) : () ),
                    join => MT::ObjectTag->join_on(
                        'tag_id',
                        {   object_datasource => $model->datasource,
                            %blog_terms,
                        },
                        { %blog_args, unique => 1 }
                    ),
                }
            )
        ];
        if (! scalar @$tags ) {
            return '';
        }
        my @tag_ids = map { $_->id, ( $_->n8d_id ? ( $_->n8d_id ) : () ) } @$tags;
        if ( @tag_ids ) {
            $extras->{ join } = [ 'MT::ObjectTag',
                'object_id',
                { tag_id => \@tag_ids, object_datasource => $model->datasource },
                { unique => 1 } ];
        }
    }
    if ( my @metadata = MT::Meta->metadata_by_class( $model ) ) {
        for my $meta ( @metadata ) {
            my $name = $meta->{ name };
            if ( $name =~ m!^field\.(.*$)! ) {
                push ( @$column_names, 'field_' . $1 );
            }
        }
    }
    push ( @$column_names, 'tags' );
    if ( %fields ) {
        my ( $col, $val ) = %fields;
        # specifies we need a join with object_meta;
        # for now, we support one join
        my $type = MT::Meta->metadata_by_name( $model, 'field.' . $col );
        if (! $type ) {
            return '';
        }
        my $field_id = '= ' . $tag . '_id';
        $extras->{ join } = [
            $model->meta_pkg,
            undef,
            {   type            => 'field.' . $col,
                $type->{ type } => $val,
                $tag . '_id'    => \$field_id,
            }
        ];
    }
    if ( my $f = MT::Component->registry( 'tags', 'filters', $ctx->stash( 'tag' ) ) ) {
        foreach my $set ( @$f ) {
            foreach my $fkey ( keys %$set ) {
                if ( exists $args->{ $fkey } ) {
                    my $handler = $set->{ $fkey }{ code }
                        ||= MT->handler_to_coderef( $set->{ $fkey }{ handler } );
                    next unless ref( $handler ) eq 'CODE';
                    local $ctx->{ terms } = $params;
                    local $ctx->{ args } = $extras;
                    $handler->( $ctx, $args, $cond );
                }
            }
        }
    }
    my @objects = $model->load( $params, $extras );
    if ( $args->{ 'shuffle' } ) {
        eval "require List::Util;";
        unless ( $@ ) {
            @objects = List::Util::shuffle( @objects );
        }
    }
    my $tokens = $ctx->stash( 'tokens' );
    my $builder = $ctx->stash( 'builder' );
    my $vars = $ctx->{ __stash }{ vars } ||= {};
    my $res = '';
    my $i = 0;
    my $old_vars = {};
    for my $key ( @$column_names ) {
        if ( my $old = $vars->{ $tag . '_' . $key } ) {
            $old_vars->{ $tag . '_' . $key } = $old;
        }
    }
    my @methods;
    if ( $tag eq 'blog' ) {
        @methods = qw/ blog_site_path blog_site_url 
            blog_archive_path blog_archive_url /;
    } elsif ( $tag eq 'asset' ) {
        @methods = qw/ asset_file_path asset_url /;
    }
    for my $object( @objects ) {
        $i++;
        my $values = $object->get_values;
        if ( my $meta = $object->meta ) {
            if ( ( ref $meta ) eq 'HASH' ) {
                for my $field( keys %$meta ) {
                    my $field_name = $field;
                    $field_name =~ s/\./_/;
                    $values->{ $field_name } = $object->$field;
                }
            }
        }
        if ( $model->isa( 'MT::Taggable' ) ) {
            if ( my @tags = $object->tags ) {
                $vars->{ $tag . '_tags' } = \@tags;
            } else {
                delete( $vars->{ $tag . '_tags' } );
            }
        }
        $ctx->stash( $object->datasource, $object );
        for my $key ( keys %$values ) {
            if ( $key !~ /password|secret/i ) {
                my $value = $values->{ $key };
                my $_key = $tag . '_' . $key;
                if ( grep( /^$_key$/, @methods ) ) {
                    $value = $object->$key;
                }
                $vars->{ $_key } = $value;
            }
        }
        local $vars->{ __counter__ } = $i;
        local $vars->{ __first__ } = 1 if $i == 1;
        local $vars->{ __first__ } = 0 if $i != 1;
        local $vars->{ __last__ }  = 1 if $i == scalar @objects;
        local $vars->{ __last__ }  = 0 if $i != scalar @objects;
        local $vars->{ __odd__ }   = ( $i % 2 ) == 1;
        local $vars->{ __even__ }  = ( $i % 2 ) == 0;
        my $out = $builder->build( $ctx, $tokens, $cond );
        for my $key ( keys %$values ) {
            delete( $vars->{ $tag . '_' . $key } );
        }
        $res .= $out;
    }
    for my $key ( keys %$old_vars ) {
        $vars->{ $tag . '_' . $key } = $old_vars->{ $key };
    }
    $res;
}

sub _filter_sample {
    my ( $ctx ) = @_;
    my $terms = $ctx->{ terms };
    my $args = $ctx->{ args };
    # Do Someting
}

1;