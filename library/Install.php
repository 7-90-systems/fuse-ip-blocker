<?php
    /**
     *  @package fuseipblocker
     *  @version 1.0
     */
    
    namespace Fuse\Plugin\IpBlocker;
    
    use Fuse\Traits\Singleton;
    
    
    class Install {
        
        use Singleton;
        
        
        
        
        /**
         *  Install our database tables.
         */
        public function installDatabase () {
            require_once (ABSPATH.'wp-admin/includes/upgrade.php');
            
            global $wpdb;
            
            // Create our blocked IP database table
            $sql = "CREATE TABLE `".$wpdb->prefix."fuseip_blocks` (
                `ip` varchar(255) NOT NULL,
                `last_blocked` datetime NOT NULL,
                `block_count` bigint UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (`ip`)
            ) ".$wpdb->get_charset_collate ().";";
            dbDelta ($sql);
            
            // Create our logs table
            $sql = "CREATE TABLE `".$wpdb->prefix."fuseip_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip` varchar(255) NOT NULL,
                `hit_time` datetime NOT NULL,
                `remote_ip` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX(`ip`),
                INDEX(`hit_time`, `remote_ip`)
            ) ".$wpdb->get_charset_collate ().";";
            dbDelta ($sql);
        } // installDatabase ()
        
    } // class Install