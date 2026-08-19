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
         *  @var string The nonce action guarding the AJAX endpoints.
         */
        const NONCE_ACTION = 'fuse-ipblocker';
        
        
        
        
        /**
         *  Set up our plugin
         */
        protected function _init () {
            // Set up the administration areas.
            add_action ('admin_menu', array ($this, 'adminMenu'));

            // Add our settings to the Fuse settings screen.
            add_filter ('fuse_settings_form_panels', array ('\Fuse\Plugin\IpBlocker\BlockStatus', 'addSettingsPanel'));
            
            // Add our AJAX functions
            add_action ('wp_ajax_fuse_ipblock_add', array ($this, 'addIpBlock'));
            add_action ('wp_ajax_fuse_ipblock_delete', array ($this, 'deleteIpBlock'));
        } // _init ()
        
        
        
        
        /**
         *  Set up the administration menu.
         */
        public function adminMenu () {
            add_submenu_page ('fusesettings', __ ('IP Blocker', 'fuseip'), __ ('IP Blocker', 'fuseip'), 'manage_options', 'ipblocker', array ($this, 'blockListPage'));
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
                    var fuseIpBlockNonce = '<?php echo esc_js (wp_create_nonce (self::NONCE_ACTION)); ?>';
                    
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
                                nonce: fuseIpBlockNonce,
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
                                nonce: fuseIpBlockNonce,
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
            
            if ($this->_checkRequest () === false) {
                wp_send_json (array (
                    'success' => false,
                    'message' => __ ('You are not allowed to do that.', 'fuseip')
                ));
            } // if ()
            
            $ip = $this->_validateIpBlock (array_key_exists ('ip', $_POST) ? wp_unslash ($_POST ['ip']) : '');
            
            if ($ip !== false) {
                $query = $wpdb->prepare ("SELECT
                    ip
                FROM ".$wpdb->prefix."fuseip_blocks
                WHERE ip = %s
                LIMIT 1", $ip);
                
                if (count ($wpdb->get_results ($query)) == 0) {
                    $wpdb->insert ($wpdb->prefix.'fuseip_blocks', array (
                        'ip' => $ip,
                        'date_added' => current_time ('mysql'),
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
                $response ['message'] = $this->_invalidIpMessage ();
            } // else
            
            wp_send_json ($response);
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
            
            if ($this->_checkRequest () === false) {
                wp_send_json (array (
                    'success' => false,
                    'message' => __ ('You are not allowed to do that.', 'fuseip')
                ));
            } // if ()
            
            $ip = array_key_exists ('ip', $_POST) ? sanitize_text_field (wp_unslash ($_POST ['ip'])) : '';
            
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
                $response ['message'] = __ ('An invalid IP address has been entered.', 'fuseip');
            } // else
            
            wp_send_json ($response);
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
            
            $ip = array_key_exists ('ip', $_GET) ? sanitize_text_field (wp_unslash ($_GET ['ip'])) : '';
            ?>
                <?php if (strlen ($ip) > 0): ?>
                
                    <h1><?php printf (__ ('Blocked IP Logs for %s', 'fuseip'), esc_html ($ip)); ?></h1>
                
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
                                        <td><?php echo esc_html ($row->remote_ip); ?></td>
                                        <td><?php echo esc_html (date ('g:i:sa j/n/Y', strtotime ($row->hit_time))); ?></td>
                                        <td><?php echo esc_html ($row->hit_url); ?></td>
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
         *  Check that this request is allowed to change the block list.
         *
         *  Both AJAX endpoints previously ran for any logged-in user with no
         *  nonce at all, so a subscriber could block or unblock any address --
         *  including the address of whoever was trying to administer the site.
         *
         *  @return bool True when the request may proceed.
         */
        protected function _checkRequest () {
            if (current_user_can ('manage_options') === false) {
                return false;
            } // if ()
            
            return check_ajax_referer (self::NONCE_ACTION, 'nonce', false) !== false;
        } // _checkRequest ()
        
        
        
        
        /**
         *  Validate an IP address or partial address for the block list.
         *
         *  A block is matched as a plain left-hand string prefix against the
         *  visitor's address, so this accepts either a complete address or the
         *  leading part of one:
         *
         *      192.0.2.1       a single IPv4 address
         *      192.0.0.        everything from 192.0.0.0 to 192.0.0.255
         *      192.0.          everything from 192.0.0.0 to 192.0.255.255
         *      2001:db8:...    a single IPv6 address
         *      2001:db8:       everything under that prefix
         *
         *  A partial address must end at a separator -- a dot for IPv4, a colon
         *  for IPv6. That is what keeps the match on a boundary: without the
         *  trailing dot, '192.0.1' would also match 192.0.10.x and 192.0.199.x.
         *
         *  @param string $ip The value entered.
         *
         *  @return string|false The value to store, or false if it is not
         *  usable.
         */
        protected function _validateIpBlock ($ip) {
            if (is_string ($ip) === false) {
                return false;
            } // if ()
            
            $ip = trim ($ip);
            
            if ($ip === '') {
                return false;
            } // if ()
            
            // Only an IPv6 address carries a colon.
            if (strpos ($ip, ':') !== false) {
                return $this->_validateIpv6Block ($ip);
            } // if ()
            
            return $this->_validateIpv4Block ($ip);
        } // _validateIpBlock ()
        
        /**
         *  Validate a complete or partial IPv4 address.
         *
         *  @param string $ip The value entered.
         *
         *  @return string|false The value to store, or false.
         */
        protected function _validateIpv4Block ($ip) {
            // A complete address.
            if (filter_var ($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $ip;
            } // if ()
            
            // A partial address has to stop at an octet boundary.
            if (substr ($ip, -1) !== '.') {
                return false;
            } // if ()
            
            $octets = explode ('.', rtrim ($ip, '.'));
            
            // One to three octets; four of them would be a complete address.
            if (count ($octets) < 1 || count ($octets) > 3) {
                return false;
            } // if ()
            
            foreach ($octets as $octet) {
                /**
                 *  No leading zeros -- '010' and '10' would be stored as
                 *  different blocks but mean the same octet, and only one of
                 *  them could ever match.
                 */
                if (preg_match ('/^(0|[1-9][0-9]{0,2})$/', $octet) !== 1) {
                    return false;
                } // if ()
                
                if (intval ($octet) > 255) {
                    return false;
                } // if ()
            } // foreach ()
            
            return $ip;
        } // _validateIpv4Block ()
        
        /**
         *  Validate a complete or partial IPv6 address.
         *
         *  @param string $ip The value entered.
         *
         *  @return string|false The value to store, or false.
         */
        protected function _validateIpv6Block ($ip) {
            $ip = strtolower ($ip);
            
            /**
             *  Something like '2001:db8::' is a valid address in its own right,
             *  but nobody types it meaning that -- they mean the range. Stored
             *  as-is it would never match 2001:db8:85a3::1, because the block
             *  is compared as text. Reject it and let the message point at the
             *  form that does work, '2001:db8:'.
             */
            if (substr ($ip, -2) === '::') {
                return false;
            } // if ()
            
            // A complete address.
            if (filter_var ($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                /**
                 *  Store it the way PHP reports an address in REMOTE_ADDR. An
                 *  IPv6 address can be written several ways, and the block is
                 *  compared as text -- so the fully expanded form a person is
                 *  most likely to paste would otherwise never match.
                 */
                $packed = inet_pton ($ip);
                
                if ($packed !== false) {
                    return inet_ntop ($packed);
                } // if ()
                
                return $ip;
            } // if ()
            
            /**
             *  A partial address has to stop at a group boundary, and cannot
             *  use '::' compression: the stored value is compared as text, so a
             *  compressed prefix would never line up against a real address.
             */
            if (substr ($ip, -1) !== ':' || strpos ($ip, '::') !== false) {
                return false;
            } // if ()
            
            $groups = explode (':', rtrim ($ip, ':'));
            
            // One to seven groups; eight would be a complete address.
            if (count ($groups) < 1 || count ($groups) > 7) {
                return false;
            } // if ()
            
            foreach ($groups as $group) {
                if (preg_match ('/^[0-9a-f]{1,4}$/', $group) !== 1) {
                    return false;
                } // if ()
            } // foreach ()
            
            return $ip;
        } // _validateIpv6Block ()
        
        /**
         *  The message shown when an entered address cannot be used.
         *
         *  @return string The message.
         */
        protected function _invalidIpMessage () {
            return __ ('That is not a usable IP address or range. Enter a full address, or the start of one ending at a separator -- 192.0.0. for IPv4, or 2001:db8: for IPv6. An IPv6 range cannot use :: shorthand.', 'fuseip');
        } // _invalidIpMessage ()
        
        
        
        
        /**
         *  Output our block list table.
         */
        protected function _blockListTable () {
            global $wpdb;
            
            $query = "SELECT
                block.ip AS ip,
                block.date_added AS date_added,
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
                                        <a href="<?php echo esc_url (admin_url ('admin.php?page=ipblocker&section=logs&ip='.urlencode ($row->ip))); ?>">
                                            <?php echo esc_html ($row->ip); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php
                                            /**
                                             *  Both cases are measuring the same thing -- how long
                                             *  it has been since this block last did anything -- so
                                             *  both are described by the same levels. A block that
                                             *  has never fired is measured from the day it was
                                             *  added instead.
                                             */
                                            $now = new \DateTime (current_time ('mysql'));
                                            $last_blocked = $row->last_blocked;
                                            $has_blocked = empty ($last_blocked) === false && $last_blocked != '0000-00-00 00:00:00';

                                            $since = new \DateTime ($has_blocked ? $last_blocked : $row->date_added);
                                            $days = intval ($now->diff ($since)->format ('%a'));

                                            $status = BlockStatus::forDays ($days);

                                            if ($has_blocked) {
                                                $summary = date ('g:i:sa j/n/Y', strtotime ($last_blocked));
                                            } // if ()
                                            else {
                                                $summary = sprintf (
                                                    _n ('No blocks recorded in %d day', 'No blocks recorded in %d days', $days, 'fuseip'),
                                                    $days
                                                );
                                            } // else
                                            ?>
                                            <span class="<?php echo esc_attr ($status ['class']); ?>">
                                                <?php echo esc_html ($summary); ?>
                                            </span>
                                            <?php
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                            /**
                                             *  A high count is worth seeing whenever the block
                                             *  last fired, so the column is levelled on its own
                                             *  rather than inheriting the date's colour. An
                                             *  ordinary count carries no class, so the column
                                             *  only draws the eye when it should.
                                             */
                                            $count = BlockStatus::forCount ($row->block_count);
                                            $total = intval ($row->block_count);

                                            if ($count ['class'] === '') {
                                                echo esc_html ($total);
                                            } // if ()
                                            else {
                                                printf (
                                                    '<span class="%s">%s</span>',
                                                    esc_attr ($count ['class']),
                                                    esc_html ($total)
                                                );
                                            } // else
                                        ?>
                                    </td>
                                    <td style="width: 20px;">
                                        <a href="#" class="delete-ip admin-red" data-ip="<?php echo esc_attr ($row->ip); ?>">
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