<?php
if( !defined( 'DOKU_INC' ) ) die();
if( !defined( 'DOKU_PLUGIN' ) ) define( 'DOKU_PLUGIN', 
        DOKU_INC . 'lib/plugins/' );
require_once (DOKU_PLUGIN . 'action.php');

class action_plugin_crosspost extends DokuWiki_Action_Plugin
{
    function register(&$controller)
    {
        $controller->register_hook( 'ACTION_ACT_PREPROCESS', 'BEFORE', $this, 
                'handle_act' );
        $controller->register_hook( 'DOKUWIKI_STARTED', 'BEFORE', $this, 
                'check_cp_redirect' );
        $controller->register_hook( 'HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 
                'print_edit_form' );
        if( $this->getConf( 'cp_add_links' ) )
        {
            $controller->register_hook( 'TPL_CONTENT_DISPLAY', 'BEFORE', $this, 
                    'add_cp_links', $this->formdata );
        }
    }

    /*
     * Save page: create and delete crossposts here
     */
    function handle_act(&$e, $param)
    {
        global $ID;
        global $INPUT;
        global $conf;
        
        if( !is_object( $INPUT ) || !$INPUT->post ||
                 !$INPUT->post->param( 'crosspost_plugin' ) ) return;
        
        $match = $INPUT->post->param( 'crosspost_to' );
        $match = preg_split( '/[\s,]+/', $match, -1, PREG_SPLIT_NO_EMPTY );
        
        $meta = p_get_metadata( $ID, 'crosspost_to' );
        $meta = preg_split( '/[\s,]+/', $meta, -1, PREG_SPLIT_NO_EMPTY );
        
        $name = noNS( $ID );
        $save = array();
        
        foreach( $match as $page )
        {
            if( strpos( $ID, ':' ) === false ) $page .= ':' . $ID;
            else $page .= ':' . $name;
            
            if( auth_quickaclcheck( $page ) < 1 ) continue;
            
            $exists = @file_exists( wikiFN( $page ) );
            
            if( $ID != $page && !$exists )
            {
                @saveWikiText( $page, "{{page>$ID}}", 'link from ' . $ID );
                p_set_metadata( $page, 
                        array('crosspost_source' => $ID 
                        ) );
            }
            else
            {
                p_set_metadata( $page, 
                        array('cache' => 'expire' 
                        ), false, false );
            }
            if( !in_array( $page, $save ) ) $save[] = $page;
        }
        foreach( $meta as $page )
        {
            if( !in_array( $page, $match ) )
            {
                @unlink( wikiFN( $page ) );
                p_set_metadata( $page, 
                        array('crosspost_source' => '','cache' => 'expire' 
                        ), false, false );
            }
        }
        
        p_set_metadata( $ID, 
                array('crosspost_to' => join( ',', $save ),'cache' => 'expire' 
                ) );
        
        $sidebar = isset( $conf['sidebar'] ) ? $conf['sidebar'] : 'sidebar';
        if( $sidebar ) p_set_metadata( $sidebar, 
                array('cache' => 'expire' 
                ), false, false );
    }

    /*
     * Redirect to "original" page if edited page is copy
     */
    function check_cp_redirect(&$e, $param)
    {
        global $ID;
        global $ACT;
        
        if( $ACT != 'edit' ) return;
        $meta = p_get_metadata( $ID, 'crosspost_source' );
        if( !$meta ) return;
        if( $meta == $ID ) return;
        send_redirect( wl( $meta ) . '?do=edit' );
    }

    /*
     * Add NS selection to edit page form
     */
    function print_edit_form(&$e, $param)
    {
        global $ID;
        global $ACT;
        global $conf;
        
        if( $ACT != 'edit' ) return;

        $namespaces = array();
        search( $namespaces, $conf['datadir'], 'search_namespaces', array() );
        $this_ns = '';

        $meta = p_get_metadata( $ID, 'crosspost_to' );
        $meta = preg_split( '/[\s,+]/', $meta, -1, PREG_SPLIT_NO_EMPTY );
        
        for( $i = 0; $i < count( $meta ); $i++ )
        {
            $ns = getNS( $meta[$i] );
            if( $meta[$i] == $ID )
            {
                array_splice( $meta, $i, 1 );
                $this_ns = $ns;
            }
            else
            {
                $meta[$i] = $ns;
            }
        }
        
        if( !$this_ns ) $this_ns = getNS( $ID );
        $xns = $this->getConf( 'cp_ns_disabled' );
        $xns = preg_split( '/[\s,+]/', $xns, -1, PREG_SPLIT_NO_EMPTY );
        $x_exact = array();
        $x_full = array();
        foreach( $xns as $x )
        {
            if( $x{0} == '!' )
            {
                $x = ltrim( $x, '!' );
                if( !in_array( $x, $x_full ) ) $x_full[] = $x;
            }
            else
                $x_exact[$x] = 1;
        }

        $links = array();
        foreach( $namespaces as $ns )
        {
            if( $ns['id'] == $this_ns ) continue;
            if( $x_exact[$ns['id']] ) continue;
            $skip = false;
            foreach( $x_full as $x )
            {
                $pos = strpos( $ns['id'], $x );
                if( $pos !== false && $pos == 0 )
                {
                    $skip = 1;
                    break;
                }
            }
            if( $skip ) continue;
            
            $link = '<a ';
            if( in_array( $ns['id'], $meta ) )
            {
                $link .= 'style="font-weight:bold;text-decoration:underline" ';
            }
            $link .= 'href="#" onclick="' . 'return cp_link_clicked(this,&quot;' .
                     $ns['id'] . '&quot)' . '" class="crosspost">' . $ns['id'] .
                     '</a>';
            $links[] = $link;
        }
        
        $input = '<b>' . $this->getlang( 'cp_to' ) . '</b><br/>' .
                 '<input type="hidden" name="crosspost_plugin" value="crosspost_plugin" />' .
                 '<input type="text" name="crosspost_to" id="crosspost_to" value="' .
                 join( ',', $meta ) . '" style="width:100%" class="crosspost" ' .
                 'onkeydown="return false;" onmousedown="return false;" ' . '/>' .
                 '<div class="crosspost_form">' . '<b>' .
                 $this->getlang( 'cp_click_to_add' ) . '</b><br/>' .
                 join( ' ', $links ) . '</div>';
        
        $e->data->insertElement( 2, $input );
    }

    private function _add_link($page)
    {
        return '<span class="crosspost"><a href="' . wl( $page ) .
                 '" class = "wikilink1 crosspost">' . $page . '</a></span>';
    }

    function add_cp_links(&$e, $param)
    {
        global $ID;
        
        $cs_source = p_get_metadata( $ID, 'crosspost_source' );
        
        $cc = p_get_metadata( $cs_source ? $cs_source : $ID, 'crosspost_to' );
        $cc = preg_split( '/[\s,+]/', $cc, -1, PREG_SPLIT_NO_EMPTY );
        if( count( $cc ) < 1 ) return;
        
        if( !in_array( $ID, $cc ) ) $cc[] = $ID;
        if( $cs_source && !in_array( $cs_source, $cc ) ) $cc[] = $cs_source;
        
        $addlink = '';
        foreach( $cc as $page )
        {
            if( $page != $ID )
            {
                $addlink .= $this->_add_link( $page ) . " ";
            }
        }
        
        if( $addlink ) ptln( '<div class="crosspost">' . $addlink . '</div>' );
    }
}
