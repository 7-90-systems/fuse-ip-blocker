<?php
    /**
     *  @package fuseipblocker
     *  @version 1.0
     *
     *  Plugin Name: Fuse IP Blocker
     *  Plugin URI: https://fusecms.org
     *  Description: Block IP addresses from accessing your site. Block by IP address or range, Works with IPV4 and IPV6.
     *  Author: 7-90 Systems
     *  Author URI: https://7-90.com.au
     *  Version: 1.0
     *  Requires at least: 6.4
     *  Requires PHP: 8.1
     *  Text Domain: fuseip
     *  Fuse Update Server: http://fusecms.org
     */
    
    namespace Fuse\Plugin\IpBlocker;
    
    
    define ('FUSE_PLUGIN_IPBLOCKER_BASE_URI', __DIR__);
    define ('FUSE_PLUGIN_IPBLOCKER_BASE_URL', plugins_url ('', __FILE__));
    
    
    
    
    /**
     *  Before anything else, let's do our checks!
     */
    fuse_ipblocker_check ();
    
    
    
    
    /**
     *  This is only useful in the admin area, so don't load unless we are in admin.
     */
    if (is_admin ()) {
        $fuse_ipblocker_setup = Setup::getInstance ();
    } // if ()
    
    
    
    
    /**
     *  Set up our installation functions.
     */
    register_activation_hook (__FILE__, '\Fuse\Plugin\IpBlocker\fuse_ipblocker_install');
        
    function fuse_ipblocker_install () {
        $install = Install::getInstance ();
        
        $install->installDatabase ();
    } // fues_ipblocker_install ()
    
    
    
    
    /**
     *  Lets do our checks
     */
    function fuse_ipblocker_check () {
        global $wpdb;
        global $block_set;
        
        $block_set = false;
        
        $query = $wpdb->prepare ("SELECT
            *
        FROM information_schema.tables
        WHERE table_schema = %s
            AND table_name = %s
        LIMIT 1", DB_NAME, $wpdb->prefix.'fuseip_blocks');
        $result = $wpdb->get_results ($query);
    
        // Only check if the database table exists
        if (count ($result) == 1) {
            $ip = $_SERVER ['REMOTE_ADDR'];
            
            $query = $wpdb->prepare ("SELECT
                ip
            FROM ".$wpdb->prefix."fuseip_blocks
            WHERE ip = SUBSTRING(%s, 1, LENGTH(ip)) 
            LIMIT 1", $ip);
            
            $db_ip = $wpdb->get_var ($query);

            if ($block_set === false && empty ($db_ip) === false) {
                $block_set = true;
                
                // We've got match, so block!
                $query = $wpdb->prepare ("UPDATE ".$wpdb->prefix."fuseip_blocks
                SET block_count = COALESCE(block_count, 0) + 1,
                    last_blocked = %s
                WHERE ip = %s
                LIMIT 1", current_time ('mysql'), $db_ip);
                
                $wpdb->query ($query);
                
                $wpdb->insert ($wpdb->prefix.'fuseip_logs', array (
                    'ip' => $db_ip,
                    'hit_time' => current_time ('mysql'),
                    'hit_url' => $_SERVER ['REQUEST_URI'],
                    'remote_ip' => $ip
                ), array (
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                ));

                echo get_option ('fuse_ipblocker_blockmessage', __ ('You are not allows to access this resource', 'fuseip'));
                header ('HTTP/1.1 403 Forbidden');
                
                die ();
            } // if ()
        } // if ()
    } // fuse_ipblocker_check ()