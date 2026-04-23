<?php
namespace local_loginsync;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function on_user_loggedin(\core\event\user_loggedin $event) {
        global $DB;

        // Solo ejecutar para usuarios autenticados via OIDC
        $userid = $event->userid;
        $user = $DB->get_record('user', ['id' => $userid], 'id, auth', MUST_EXIST);

        if ($user->auth !== 'oidc') {
            return;
        }

        // Ejecutar cohort sync de local_o365
        try {
            $cohortsync = new \local_o365\task\cohortsync();
            $cohortsync->execute();
        } catch (\Exception $e) {
            debugging('local_loginsync: error en cohortsync - ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Ejecutar sincronización de matriculación de cohortes
        try {
            $enrolsync = new \enrol_cohort\task\enrol_cohort_sync();
            $enrolsync->execute();
        } catch (\Exception $e) {
            debugging('local_loginsync: error en enrol_cohort_sync - ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
