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
            
            // Add our AJAX functions
            add_action ('wp_ajax_fuse_ipblock_add', array ($this, 'addIpBlock'));
            add_action ('wp_ajax_fuse_ipblock_delete', array ($this, 'deleteIpBlock'));
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
                
                <?php
                    if (array_key_exists ('section', $_GET) && $_GET ['section'] == 'logs') {
                        $this->_showLogsPage ();
                    } // if ()
                    else {
                        $this->_showBlockListPage ();
                    } // else
                ?>
                             
            </div>
            <script type="text/javascript">
                
                jQuery (document).ready (function () {
                    // Delete a blocked IP
                    jQuery ('#fuse-ipblocker-list-table-container').on ('click', '.delete-ip', function (e) {
                        e.preventDefault ();
                        
                        let btn = jQuery (this);
                        let row = btn.closest ('tr');
                        
                        row.hide ();
                        
                        jQuery.ajax ({
                            url: ajaxurl,
                            data: {
                                action: 'fuse_ipblock_delete',
                                ip: btn.data ('ip')
                            },
                            method: 'post',
                            dataType: 'json',
                            success: function (response) {
                                if (response ['success'] === true) {
                                    row.remove ();
                                } // if ()
                                else {
                                    alert (response.message);
                                    row.show ();
                                } // else
                            }
                        });
                    });
                    
                    // Add a new IP
                    jQuery ('#fuse-ipblock-add-ip-button').click (function (e) {
                        e.preventDefault ();
                        
                        let btn = jQuery (this);
                        let field = jQuery ('#fuse-ipblocker-new-ip');
                        
                        let current_btn_text = btn.text ();
                        
                        btn.text ('Saving...');
                        btn.prop ('disabled', true);
                        
                        jQuery.ajax ({
                            url: ajaxurl,
                            data: {
                                action: 'fuse_ipblock_add',
                                ip: field.val ()
                            },
                            method: 'post',
                            dataType: 'json',
                            success: function (response) {
                                if (response ['success'] === true) {
                                    field.val ('');
                                    jQuery ('#fuse-ip-blocker-list-table').replaceWith (response ['table']);
                                } // if ()
                                else {
                                    alert (response.message);
                                } // else
                            },
                            complete: function () {
                                btn.text (current_btn_text);
                                btn.prop ('disabled', false);
                            }
                        });
                    });
                });
                
            </script>
            <?php
        } // blockListPage ()
        
        
        
        
        /**
         *  Add a new IP address
         */
        public function addIpBlock () {
            global $wpdb;
            
            $response = array (
                'success' => false,
                'message' => __ ('An unknown error has occured', 'fuseip')
            );
            
            $ip = array_key_exists ('ip', $_POST) ? $_POST ['ip'] : '';
            
            if (strlen ($ip) > 1) {
                $query = $wpdb->prepare ("SELECT
                    ip
                FROM ".$wpdb->prefix."fuseip_blocks
                WHERE ip = %s
                LIMIT 1", $ip);
                
                if (count ($wpdb->get_results ($query)) == 0) {
                    $wpdb->insert ($wpdb->prefix.'fuseip_blocks', array (
                        'ip' => $ip,
                        'last_blocked' => '0000-00-00 00:00:00',
                        'block_count' => 0
                    ),
                    array (
                        '%s',
                        '%s',
                        '%s'
                    ));
                    
                    $response = array (
                        'success' => true,
                        'table' => $this->_blockListTable ()
                    );
                } // if ()
                else {
                    $response ['message'] = __ ('That IP address already exists and cannot be added again', 'fuseip');
                } // else
            } // if ()
            else {
                $response ['message'] = __ ('An invalid IP address has bene entered.', 'fuseip');
            } // else
            
            echo json_encode ($response);
            die ();
        } // addIpBlock ()
        
        /**
         *  Delete an IP block.
         */
        public function deleteIpBlock () {
            global $wpdb;
            
            $response = array (
                'success' => false,
                'message' => __ ('An unknown error has occured', 'fuseip')
            );
            
            $ip = array_key_exists ('ip', $_POST) ? $_POST ['ip'] : '';
            
            if (strlen ($ip) > 1) {
                $query = $wpdb->prepare ("SELECT
                    ip
                FROM ".$wpdb->prefix."fuseip_blocks
                WHERE ip = %s
                LIMIT 1", $ip);
                
                if (count ($wpdb->get_results ($query)) == 1) {
                    $wpdb->delete ($wpdb->prefix.'fuseip_logs', array (
                        'ip' => $ip
                    ), array (
                        '%s'
                    ));
                    
                    $wpdb->delete ($wpdb->prefix.'fuseip_blocks', array (
                        'ip' => $ip
                    ), array (
                        '%s'
                    ));
                    
                    $response = array (
                        'success' => true
                    );
                } // if ()
                else {
                    $response ['message'] = __ ('An invalid IP address was requesed. Please try again.', 'fuseip');
                } // else
            } // if ()
            else {
                $response ['message'] = __ ('An invalid IP address has bene entered.', 'fuseip');
            } // else
            
            echo json_encode ($response);
            die ();
        } // deleteIpBlock ()
        
        
        
        
        /**
         *  Show the block list page.
         */
        protected function _showBlockListPage () {
            ?>
                <h1><?php _e ('Block IP Addresses', 'fuseip'); ?></h1>
                
                <div id="fuse-ipblocker-list-table-container">
                    <?php
                        echo $this->_blockListTable ();
                    ?>
                </div>
                
                <p>&nbsp;</p>
                <hr />
                <p>&nbsp;</p>
                
                <h3><?php _e ('Block a new IP Address', 'fuseip'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e ('IP address to block', 'fuseip'); ?></th>
                        <td>
                            <input type="text" id="fuse-ipblocker-new-ip" name="fuseipblock-new" value="" class="regular-text" />
                        </td>
                    </tr>
                </table>
                <p>
                    <button id="fuse-ipblock-add-ip-button" class="button button-primary"><?php _e ('Add new IP block', 'fuseip'); ?></button>
                </p>
                <p class="description">
                    <?php _e ('You can block a full IP address or a range by removing parts.', 'fuseip'); ?>
                </p>

                <p class="description">
                    <?php _e ('IPV4 - 127.0.0.1 blocks a single address, or 127.0.0. blocks all IPs from .0 to .255', 'fuseip'); ?>
                </p>
                <p class="description">
                    <?php _e ('IPV6 - 2001:0db8:85a3:0000:0000:8a2e:0370:7334 blocks a single address, or 2001:0db8:85a3:0000:0000:8a2e:0370: blocks a range.', 'fuseip'); ?>
                </p>   
            <?php
        } // _showBlockListPage ()
        
        /**
         *  Show the logs page.
         */
        protected function _showLogsPage () {
            global $wpdb;
            
            $ip = array_key_exists ('ip', $_GET) ? $_GET ['ip'] : '';
            ?>
                <?php if (strlen ($ip) > 0): ?>
                
                    <h1><?php printf (__ ('Blocked IP Logs for %s', 'fuseip'), $ip); ?></h1>
                
                    <?php
                        $query = $wpdb->prepare ("SELECT
                            *
                        FROM ".$wpdb->prefix."fuseip_logs
                        WHERE ip = %s
                        ORDER BY hit_time DESC, remote_ip ASC", $ip);
                        $results = $wpdb->get_results ($query);
                    ?>
                    
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e ('Remote IP blocked', 'fuseip'); ?></th>
                                <th><?php _e ('Block time', 'fuseip'); ?></th>
                                <th><?php _e ('Request URI', 'fuseip'); ?></th>
                            </tr>
                        <thead>
                        <tfoot>
                            <tr>
                                <th><?php _e ('Remote IP blocked', 'fuseip'); ?></th>
                                <th><?php _e ('Block time', 'fuseip'); ?></th>
                                <th><?php _e ('Request URI', 'fuseip'); ?></th>
                            </tr>
                        <tfoot>
                        <tbody>
                            <?php if (count ($results) > 0): ?>
                            
                                <?php foreach ($results as $row): ?>
                                
                                    <tr>
                                        <td><?php echo $row->remote_ip; ?></td>
                                        <td><?php echo date ('g:i:sa j/n/Y', strtotime ($row->hit_time)); ?></td>
                                        <td><?php echo $row->hit_url; ?></td>
                                    </tr>
                                
                                <?php endforeach; ?>
                            
                            <?php else: ?>
                            
                                <tr>
                                    <td colspan="2" class="admin-bold"><?php _e ('No blocks recorded for this IP address', 'fuseip'); ?></td>
                                </tr>
                            
                            <?php endif; ?>
                        <tbody>
                    </table>
                
                <?php else: ?>
                
                    <h1><?php _e ('Blocked IP Logs', 'fuseip'); ?></h1>
                
                    <p class="admin-bold amdin-red"><?php _e ('Invalid IP address requested.', 'fuseip'); ?></p>
                
                <?php endif; ?>
            <?php
        } // showLogsPage ()
        
        
        
        
        /**
         *  Output our block list table.
         */
        protected function _blockListTable () {
            global $wpdb;
            
            $query = "SELECT
                block.ip AS ip,
                block.last_blocked AS last_blocked,
                block.block_count AS block_count
            FROM ".$wpdb->prefix."fuseip_blocks AS block
            ORDER BY block.ip ASC";
            $result = $wpdb->get_results ($query);
            
            ob_start ();
            ?>
                <table id="fuse-ip-blocker-list-table" class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e ('IP Address / Range', 'fuseip'); ?></th>
                            <th><?php _e ('Last Blocked', 'fuseip'); ?></th>
                            <th><?php _e ('Block Count', 'fuseip'); ?></th>
                            <th style="width: 20px;">&nbsp;</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th><?php _e ('IP Address / Range', 'fuseip'); ?></th>
                            <th><?php _e ('Last Blocked', 'fuseip'); ?></th>
                            <th><?php _e ('Block Count', 'fuseip'); ?></th>
                            <th style="width: 20px;">&nbsp;</th>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php if (count ($result) > 0): ?>
                            
                            <?php foreach ($result as $row): ?>
                                
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url (admin_url ('tools.php?page=ipblocker&section=logs&ip='.urlencode ($row->ip))); ?>">
                                            <?php echo $row->ip; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php
                                            $date = $row->last_blocked;
                                            
                                            if ($date == '0000-00-00 00:00:00') {
                                                echo '<span class="admin-light admin-italic">'.__ ('No blocks recorded', 'fuseip').'</span>';
                                            } // if ()
                                            else {
                                                echo date ('g:i:sa j/n/Y', strtotime ($row->last_blocked));
                                            } // else
                                        ?>
                                    </td>
                                    <td><?php echo $row->block_count; ?></td>
                                    <td style="width: 20px;">
                                        <a href="#" class="delete-ip admin-red" data-ip="<?php esc_attr_e ($row->ip); ?>">
                                            <span class="dashicons dashicons-dismiss"></span>
                                        </a>
                                    </td>
                                </tr>
                                
                            <?php endforeach; ?>
                            
                        <?php else: ?>
                            
                            <tr>
                                <td colspan="4" style="text-align: center"><?php _e ('No blocks recorded', 'fuseip'); ?></td>
                            </tr>
                            
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php
            $html = ob_get_contents ();
            ob_end_clean ();
            
            return $html;
        } // blockListTable ()
        
    } // class Setup