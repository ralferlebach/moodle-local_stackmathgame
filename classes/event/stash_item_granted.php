<?php
namespace local_stackmathgame\event;

defined('MOODLE_INTERNAL') || die();

class stash_item_granted extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_stackmathgame_profile';
    }

    public static function get_name(): string {
        return get_string('event_stash_item_granted', 'local_stackmathgame');
    }

    public function get_description(): string {
        return 'Local stash-like item granted in STACK Math Game.';
    }
}
