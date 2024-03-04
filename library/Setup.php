<?php
    /**
     *  @package fuseipblocker
     *  @version 1.0
     */
    
    namespace Fuse\Plugin\IpBlocker;
    
    use Fuse\Traits\Singleton;
    
    
    class Setup {
        
        use Singleton;
        
        
        
        
        /**
         *  Set up our plugin
         */
        protected function _init () {
            // Set up the administration areas.
            add_action ('admin_menu', array ($this, 'adminMenu'));
        } // _init ()
        
        
        
        
        /**
         *  Set up the administration menu.
         */
        public function adminMenu () {
            add_management_page (__ ('IP Blocker', 'fuseip'), __ ('IP Blocker', 'fuseip'), 'manage_options', 'ipblocker', array ($this, 'blockListPage'));
        } // adminMenu ()
        
        
        
        
        /**
         *  Set up the block list page.
         */
        public function blockListPage () {
            ?>
            <div class="wrap">
                
                <h1><?php _e ('Block IP Addresses', 'fuseip'); ?></h1>
                
                <?php
                    echo $this->_blockListTable ();
                ?>
                
            </div>
            <?php
        } // blockListPage ()
        
        
        
        
        /**
         *  Output our block list table.
         */
        protected function _blockListTable () {
            
            
            ob_start ();
            ?>
                <table id="fise-ip-blocker-list-table" class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e ('IP Address / Range', 'fuseip'); ?></th>
                            <th><?php _e ('Last Blocked', 'fuseip'); ?></th>
                            <th><?php _e ('Block Count', 'fuseip'); ?></th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th><?php _e ('IP Address / Range', 'fuseip'); ?></th>
                            <th><?php _e ('Last Blocked', 'fuseip'); ?></th>
                            <th><?php _e ('Block Count', 'fuseip'); ?></th>
                        </tr>
                    </tfoot>
                    <tbody>
                        <tr>
                            <td><?php _e ('IP Address / Range', 'fuseip'); ?></td>
                            <td><?php _e ('Last Blocked', 'fuseip'); ?></td>
                            <td><?php _e ('Block Count', 'fuseip'); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php
            $html = ob_get_contents ();
            ob_end_clean ();
            
            return $html;
        } // blockListTable ()
        
    } // class Setup