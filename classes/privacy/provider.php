<?php
namespace local_stackmathgame\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_stackmathgame.
 *
 * Fixed issues:
 * 1. The original class implemented BOTH null_provider AND plugin\provider.
 *    These interfaces are mutually exclusive: null_provider says "I store no
 *    data" while plugin\provider says "I handle data export/deletion".
 *    Implementing both causes a PHP fatal error on newer Moodle versions.
 * 2. The plugin DOES store personal data (profile, eventlog tables keyed by
 *    userid) so null_provider was incorrect. The full provider interface must
 *    be implemented with real export and deletion logic.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe all personal data fields stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_stackmathgame_profile',
            [
                'userid'           => 'privacy:metadata:profile:userid',
                'labelid'          => 'privacy:metadata:profile:labelid',
                'score'            => 'privacy:metadata:profile:score',
                'xp'               => 'privacy:metadata:profile:xp',
                'levelno'          => 'privacy:metadata:profile:levelno',
                'softcurrency'     => 'privacy:metadata:profile:softcurrency',
                'hardcurrency'     => 'privacy:metadata:profile:hardcurrency',
                'avatarconfigjson' => 'privacy:metadata:profile:avatarconfigjson',
                'progressjson'     => 'privacy:metadata:profile:progressjson',
                'statsjson'        => 'privacy:metadata:profile:statsjson',
                'flagsjson'        => 'privacy:metadata:profile:flagsjson',
                'lastquizid'       => 'privacy:metadata:profile:lastquizid',
                'lastaccess'       => 'privacy:metadata:profile:lastaccess',
                'timecreated'      => 'privacy:metadata:profile:timecreated',
            ],
            'privacy:metadata:profile'
        );

        $collection->add_database_table(
            'local_stackmathgame_eventlog',
            [
                'userid'      => 'privacy:metadata:eventlog:userid',
                'labelid'     => 'privacy:metadata:eventlog:labelid',
                'quizid'      => 'privacy:metadata:eventlog:quizid',
                'questionid'  => 'privacy:metadata:eventlog:questionid',
                'eventtype'   => 'privacy:metadata:eventlog:eventtype',
                'payloadjson' => 'privacy:metadata:eventlog:payloadjson',
                'timecreated' => 'privacy:metadata:eventlog:timecreated',
            ],
            'privacy:metadata:eventlog'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user data for the specified user.
     *
     * Game data is stored at system context since labels cross courses.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $hasprofile = $DB->record_exists('local_stackmathgame_profile', ['userid' => $userid]);
        $hasevent   = $DB->record_exists('local_stackmathgame_eventlog', ['userid' => $userid]);

        if ($hasprofile || $hasevent) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            'SELECT DISTINCT userid FROM {local_stackmathgame_profile}',
            []
        );
    }

    /**
     * Export all data for the supplied userid and contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // Export profile records.
            $profiles = $DB->get_records('local_stackmathgame_profile', ['userid' => $userid]);
            if ($profiles) {
                writer::with_context($context)->export_data(
                    ['local_stackmathgame', 'profiles'],
                    (object)['profiles' => array_values(array_map(
                        static function(\stdClass $p): array {
                            return [
                                'labelid'    => (int)$p->labelid,
                                'score'      => (int)$p->score,
                                'xp'         => (int)$p->xp,
                                'levelno'    => (int)$p->levelno,
                                'lastaccess' => \core_privacy\local\request\transform::datetime($p->lastaccess),
                                'timecreated' => \core_privacy\local\request\transform::datetime($p->timecreated),
                            ];
                        },
                        $profiles
                    ))]
                );
            }

            // Export event log.
            $events = $DB->get_records('local_stackmathgame_eventlog', ['userid' => $userid], 'timecreated ASC');
            if ($events) {
                writer::with_context($context)->export_data(
                    ['local_stackmathgame', 'eventlog'],
                    (object)['events' => array_values(array_map(
                        static function(\stdClass $e): array {
                            return [
                                'eventtype'  => $e->eventtype,
                                'quizid'     => (int)$e->quizid,
                                'timecreated' => \core_privacy\local\request\transform::datetime($e->timecreated),
                            ];
                        },
                        $events
                    ))]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->delete_records('local_stackmathgame_eventlog', []);
        $DB->delete_records('local_stackmathgame_profile', []);
    }

    /**
     * Delete all data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->delete_records('local_stackmathgame_eventlog', ['userid' => $userid]);
            $DB->delete_records('local_stackmathgame_profile',  ['userid' => $userid]);
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('local_stackmathgame_eventlog', ['userid' => (int)$userid]);
            $DB->delete_records('local_stackmathgame_profile',  ['userid' => (int)$userid]);
        }
    }
}
