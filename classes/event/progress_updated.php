<?php
namespace local_stackmathgame\event;

defined('MOODLE_INTERNAL') || die();

class progress_updated extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_stackmathgame_profile';
    }

    public static function get_name(): string {
        return get_string('event_progress_updated', 'local_stackmathgame');
    }

    public function get_description(): string {
        return 'Progress updated by STACK Math Game.';
    }
}
