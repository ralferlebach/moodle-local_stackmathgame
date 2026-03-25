<?php
namespace local_stackmathgame\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider: no personal data stored by this plugin itself.
 */
class provider implements
    \core_privacy\local\metadata\null_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        // No-op: no user-specific data stored in plugin tables.
    }

    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist): void {
        // No-op: no user-specific data stored in plugin tables.
    }

    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist): void {
        // No-op: no user-specific data stored in plugin tables.
    }
}
