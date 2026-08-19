<?php
    /**
     *  @package fuseipblocker
     *
     *  Works out how a block should be described and styled, based on how long
     *  it has been since the block last did anything.
     *
     *  A block that has just fired is a live problem; one that has not fired in
     *  months probably is not. The block list colours each row on that basis,
     *  and the day counts that separate the levels are site settings rather
     *  than fixed numbers.
     *
     *  @filter fuse_settings_form_panels
     *  @filter fuse_ipblocker_status_levels
     */

    namespace Fuse\Plugin\IpBlocker;

    use Fuse\Forms\Component;


    class BlockStatus {

        /**
         *  @var string The settings that hold the day count for each level.
         */
        const OPTION_NEW = 'ipblocker_days_new';
        const OPTION_MATURE = 'ipblocker_days_mature';
        const OPTION_GOOD = 'ipblocker_days_good';

        /**
         *  @var int The day count each level falls back to when unset.
         */
        const DEFAULT_NEW = 15;
        const DEFAULT_MATURE = 30;
        const DEFAULT_GOOD = 60;




        /**
         *  Block direct instantiation. Everything here is static.
         */
        private function __construct () {}




        /**
         *  Get the day count for each level.
         *
         *  The levels have to climb, or a block could fall into more than one
         *  of them. Rather than refuse a set of settings that do not, each
         *  level is held at or above the one below it, so the block list stays
         *  sensible whatever has been typed in.
         *
         *  @return array The day counts, keyed 'new', 'mature' and 'good'.
         */
        public static function getThresholds () {
            $new = max (0, intval (get_fuse_option (self::OPTION_NEW, self::DEFAULT_NEW)));
            $mature = max ($new, intval (get_fuse_option (self::OPTION_MATURE, self::DEFAULT_MATURE)));
            $good = max ($mature, intval (get_fuse_option (self::OPTION_GOOD, self::DEFAULT_GOOD)));

            return array (
                'new' => $new,
                'mature' => $mature,
                'good' => $good
            );
        } // getThresholds ()




        /**
         *  Get the level a number of days falls into.
         *
         *  @param int $days The number of days since the block last did
         *  anything.
         *
         *  @return array The level, with 'key', 'label' and 'class'.
         */
        public static function forDays ($days) {
            $days = max (0, intval ($days));
            $thresholds = self::getThresholds ();

            $levels = apply_filters ('fuse_ipblocker_status_levels', array (
                'new' => array (
                    'key' => 'new',
                    'label' => __ ('New', 'fuseip'),
                    'class' => 'admin-red admin-bold'
                ),
                'mature' => array (
                    'key' => 'mature',
                    'label' => __ ('Mature', 'fuseip'),
                    'class' => 'admin-red'
                ),
                'good' => array (
                    'key' => 'good',
                    'label' => __ ('Good', 'fuseip'),
                    'class' => 'admin-green'
                ),
                'clear' => array (
                    'key' => 'clear',
                    'label' => __ ('Clear', 'fuseip'),
                    'class' => 'admin-green admin-bold'
                )
            ));

            if ($days <= $thresholds ['new']) {
                return $levels ['new'];
            } // if ()

            if ($days <= $thresholds ['mature']) {
                return $levels ['mature'];
            } // if ()

            if ($days <= $thresholds ['good']) {
                return $levels ['good'];
            } // if ()

            return $levels ['clear'];
        } // forDays ()




        /**
         *  Add our settings panel to the Fuse settings screen.
         *
         *  Registered from this plugin rather than by editing the framework, so
         *  the settings live with the feature that uses them.
         *
         *  @param array $panels The panels already on the settings form.
         *
         *  @return array The panels with ours added.
         */
        public static function addSettingsPanel ($panels) {
            if (is_array ($panels) === false) {
                $panels = array ();
            } // if ()

            $thresholds = self::getThresholds ();

            $panels [] = new Component\Panel ('ipblocker', __ ('IP Blocker', 'fuseip'), array (
                new Component\Field\Number (self::OPTION_NEW, __ ('New, up to', 'fuseip'), $thresholds ['new'], array (
                    'min' => 0,
                    'step' => 1,
                    'description' => __ ('Days since a block last stopped a request, below which it is treated as new. Shown in bold red.', 'fuseip')
                )),
                new Component\Field\Number (self::OPTION_MATURE, __ ('Mature, up to', 'fuseip'), $thresholds ['mature'], array (
                    'min' => 0,
                    'step' => 1,
                    'description' => __ ('Shown in red.', 'fuseip')
                )),
                new Component\Field\Number (self::OPTION_GOOD, __ ('Good, up to', 'fuseip'), $thresholds ['good'], array (
                    'min' => 0,
                    'step' => 1,
                    'description' => __ ('Shown in green. Anything older than this counts as clear, and is shown in bold green.', 'fuseip')
                ))
            ));

            return $panels;
        } // addSettingsPanel ()

    } // class BlockStatus
