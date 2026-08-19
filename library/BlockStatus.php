<?php
    /**
     *  @package fuseipblocker
     *
     *  Works out how a block should be styled, on two separate measures: how
     *  long it has been since the block last did anything, and how many times
     *  it has fired.
     *
     *  A block that has just fired is a live problem; one that has not fired in
     *  months probably is not. Separately, a block that has stopped a request
     *  hundreds of times is worth looking at whenever it last fired. The block
     *  list colours the date on the first measure and the count on the second,
     *  and the numbers that separate the levels are site settings rather than
     *  fixed ones.
     *
     *  @filter fuse_settings_form_panels
     *  @filter fuse_ipblocker_status_levels
     *  @filter fuse_ipblocker_count_levels
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
         *  @var string The settings that hold the block count for each level.
         */
        const OPTION_WARNING = 'ipblocker_count_warning';
        const OPTION_SEVERE = 'ipblocker_count_severe';

        /**
         *  @var int The day count each level falls back to when unset.
         */
        const DEFAULT_NEW = 15;
        const DEFAULT_MATURE = 30;
        const DEFAULT_GOOD = 60;

        /**
         *  @var int The block count each level falls back to when unset.
         */
        const DEFAULT_WARNING = 20;
        const DEFAULT_SEVERE = 50;




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
         *  The block list uses the class only -- the level is shown by colour,
         *  not by a name printed beside the date. 'label' is still carried on
         *  the level for anything hanging off the filter that wants to name it.
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
         *  Get the block count for each level.
         *
         *  Severe is held at or above warning for the same reason the day
         *  counts are held in order: a set of settings that crossed over would
         *  put a block in both levels at once, and holding them is kinder than
         *  refusing to save.
         *
         *  @return array The block counts, keyed 'warning' and 'severe'.
         */
        public static function getCountThresholds () {
            $warning = max (0, intval (get_fuse_option (self::OPTION_WARNING, self::DEFAULT_WARNING)));
            $severe = max ($warning, intval (get_fuse_option (self::OPTION_SEVERE, self::DEFAULT_SEVERE)));

            return array (
                'warning' => $warning,
                'severe' => $severe
            );
        } // getCountThresholds ()




        /**
         *  Get the level a block count falls into.
         *
         *  The thresholds are floors, not ceilings -- the opposite way round to
         *  the day counts, because a high count is the bad end of this measure
         *  while a high day count is the good end of that one.
         *
         *  Everything under the warning count is ordinary and is left with no
         *  class at all, so the column only draws the eye when it should.
         *
         *  @param int $count The number of times the block has fired.
         *
         *  @return array The level, with 'key', 'label' and 'class'.
         */
        public static function forCount ($count) {
            $count = max (0, intval ($count));
            $thresholds = self::getCountThresholds ();

            $levels = apply_filters ('fuse_ipblocker_count_levels', array (
                'normal' => array (
                    'key' => 'normal',
                    'label' => __ ('Normal', 'fuseip'),
                    'class' => ''
                ),
                'warning' => array (
                    'key' => 'warning',
                    'label' => __ ('Warning', 'fuseip'),
                    'class' => 'admin-red'
                ),
                'severe' => array (
                    'key' => 'severe',
                    'label' => __ ('Severe', 'fuseip'),
                    'class' => 'admin-red admin-bold'
                )
            ));

            if ($count >= $thresholds ['severe']) {
                return $levels ['severe'];
            } // if ()

            if ($count >= $thresholds ['warning']) {
                return $levels ['warning'];
            } // if ()

            return $levels ['normal'];
        } // forCount ()




        /**
         *  Add our settings panel to the Fuse settings screen.
         *
         *  Registered from this plugin rather than by editing the framework, so
         *  the settings live with the feature that uses them.
         *
         *  The two measures are separate groups. They are both plain numbers in
         *  a column, and five of those in one list gives no clue which are days
         *  and which are counts.
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
            $counts = self::getCountThresholds ();

            $panels [] = new Component\Panel ('ipblocker', __ ('IP Blocker', 'fuseip'), array (
                new Component\Field\Group ('ipblocker_age', __ ('Block age', 'fuseip'), array (
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
                )),
                new Component\Field\Group ('ipblocker_count', __ ('Block count', 'fuseip'), array (
                    new Component\Field\Number (self::OPTION_WARNING, __ ('Warning, from', 'fuseip'), $counts ['warning'], array (
                        'min' => 0,
                        'step' => 1,
                        'description' => __ ('Times a block has stopped a request, at or above which the count is shown in red.', 'fuseip')
                    )),
                    new Component\Field\Number (self::OPTION_SEVERE, __ ('Severe, from', 'fuseip'), $counts ['severe'], array (
                        'min' => 0,
                        'step' => 1,
                        'description' => __ ('Shown in bold red. Anything below the warning count is left unstyled.', 'fuseip')
                    ))
                ))
            ));

            return $panels;
        } // addSettingsPanel ()

    } // class BlockStatus
