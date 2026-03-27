<?php
namespace local_stackmathgame\event;

defined('MOODLE_INTERNAL') || die();

class question_solved extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_stackmathgame_profile';
    }

    public static function get_name(): string {
        return get_string('event_question_solved', 'local_stackmathgame');
    }

    public function get_description(): string {
        return 'Question solved in STACK Math Game.';
    }
}
