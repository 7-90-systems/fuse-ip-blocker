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
         *  @var string The option holding the installed schema version.
         */
        const OPTION_SCHEMA = 'fuse_ipblocker_schema';
        
        /**
         *  @var int The schema version this code expects.
         *
         *  1   the original two tables
         *  2   description on a block
         *  3   the whitelist table
         */
        const SCHEMA_VERSION = 3;
        
        
        
        
        /**
         *  Bring the tables up to date if they are behind.
         *
         *  Activation is the only thing that used to build the tables, so a
         *  site that updates the plugin without deactivating it first would
         *  never get a new column. This runs on every admin load, does nothing
         *  but read an option once the schema is current, and hands off to
         *  dbDelta when it is not -- dbDelta adds what is missing and leaves
         *  what is already there alone.
         */
        public function maybeUpgrade () {
            if (intval (get_option (self::OPTION_SCHEMA, 0)) >= self::SCHEMA_VERSION) {
                return;
            } // if ()
            
            $this->installDatabase ();
        } // maybeUpgrade ()
        
        
        
        
        /**
         *  Install our database tables.
         *
         *  dbDelta creates what is missing and alters what has changed, so this
         *  is safe to run against an existing install as well as a new one.
         */
        public function installDatabase () {
            require_once (ABSPATH.'wp-admin/includes/upgrade.php');
            
            global $wpdb;
            
            // Create our blocked IP database table
            $sql = "CREATE TABLE `".$wpdb->prefix."fuseip_blocks` (
                `ip` varchar(255) NOT NULL,
                `description` varchar(255) NOT NULL DEFAULT '',
                `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_blocked` datetime NOT NULL,
                `block_count` bigint UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY  (`ip`)
            ) ".$wpdb->get_charset_collate ().";";
            dbDelta ($sql);
            
            // Create our logs table
            $sql = "CREATE TABLE `".$wpdb->prefix."fuseip_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip` varchar(255) NOT NULL,
                `hit_time` datetime NOT NULL,
                `hit_url` TEXT NOT NULL,
                `remote_ip` varchar(255) NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `ip` (`ip`),
                KEY `hit_time_remote_ip` (`hit_time`,`remote_ip`)
            ) ".$wpdb->get_charset_collate ().";";
            dbDelta ($sql);
            
            /**
             *  Create our whitelist table.
             *
             *  The same shape as the blocks table, because it is matched the
             *  same way -- as a left-hand prefix of the visitor's address -- so
             *  an entry can be one address or a range. last_allowed and
             *  allow_count only move when an entry actually overrides a block,
             *  not on every request from a whitelisted address.
             */
            $sql = "CREATE TABLE `".$wpdb->prefix."fuseip_whitelist` (
                `ip` varchar(255) NOT NULL,
                `description` varchar(255) NOT NULL DEFAULT '',
                `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_allowed` datetime NOT NULL,
                `allow_count` bigint UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY  (`ip`)
            ) ".$wpdb->get_charset_collate ().";";
            dbDelta ($sql);
            
            update_option (self::OPTION_SCHEMA, self::SCHEMA_VERSION);
        } // installDatabase ()
        
    } // class Install