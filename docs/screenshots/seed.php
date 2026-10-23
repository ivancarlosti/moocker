<?php
// Seeds the demo category, courses and users used by the README screenshots.
//
// Runs inside the Moocker container after Moodle has been installed:
//   docker exec -u www-data <container> php /tmp/moocker-seed.php
//
// Prints `CATEGORY_ID=<id>` and `COURSE_ID=<id>` on the last lines so
// generate.sh can point the capture script at the demo content.

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);

require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

global $CFG, $DB;

// The demo accounts do not need welcome e-mails; a fresh install has no mail
// transport configured and PHP would only log "Invalid address" errors.
$CFG->noemailever = true;

/**
 * Returns the demo category, creating it on the first run.
 *
 * @return core_course_category
 */
function moocker_seed_category(): core_course_category {
	global $DB;

	$record = $DB->get_record('course_categories', ['idnumber' => 'MOOCKER-DEMO']);
	if ($record) {
		return core_course_category::get($record->id, MUST_EXIST, true);
	}

	return core_course_category::create([
		'name' => 'Moocker Demo',
		'idnumber' => 'MOOCKER-DEMO',
		'description' => '<p>Sample category created by the Moocker screenshot generator.</p>',
		'descriptionformat' => FORMAT_HTML,
		'visible' => 1,
	]);
}

/**
 * Returns a course by shortname, creating it when missing.
 *
 * @param int $categoryid
 * @param string $shortname
 * @param string $fullname
 * @param string $summary
 * @return stdClass
 */
function moocker_seed_course(int $categoryid, string $shortname, string $fullname, string $summary): stdClass {
	global $DB;

	$course = $DB->get_record('course', ['shortname' => $shortname]);
	if ($course) {
		return $course;
	}

	$course = create_course((object) [
		'fullname' => $fullname,
		'shortname' => $shortname,
		'category' => $categoryid,
		'summary' => $summary,
		'summaryformat' => FORMAT_HTML,
		'format' => 'topics',
		'startdate' => time() - (7 * DAYSECS),
		'visible' => 1,
		'enablecompletion' => 1,
	]);

	course_create_sections_if_missing($course, [1, 2, 3, 4]);

	return $course;
}

/**
 * Adds a label to a course section.
 *
 * @param stdClass $course
 * @param int $section
 * @param string $idnumber
 * @param string $name
 * @param string $html
 * @return void
 */
function moocker_seed_label(stdClass $course, int $section, string $idnumber, string $name, string $html): void {
	global $DB;

	$module = $DB->get_record('modules', ['name' => 'label'], '*', MUST_EXIST);
	$moduleinfo = (object) [
		'modulename' => 'label',
		'module' => $module->id,
		'course' => $course->id,
		'section' => $section,
		'visible' => 1,
		'visibleoncoursepage' => 1,
		'name' => $name,
		'intro' => $html,
		'introformat' => FORMAT_HTML,
		'groupmode' => 0,
		'groupingid' => 0,
		'completion' => 0,
		'completionview' => 0,
		'completionexpected' => 0,
		'cmidnumber' => $idnumber,
		'showdescription' => 0,
	];

	add_moduleinfo($moduleinfo, $course);
}

/**
 * Adds a page resource to a course section.
 *
 * @param stdClass $course
 * @param int $section
 * @param string $idnumber
 * @param string $name
 * @param string $html
 * @return void
 */
function moocker_seed_page(stdClass $course, int $section, string $idnumber, string $name, string $html): void {
	global $DB;

	$module = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
	$moduleinfo = (object) [
		'modulename' => 'page',
		'module' => $module->id,
		'course' => $course->id,
		'section' => $section,
		'visible' => 1,
		'visibleoncoursepage' => 1,
		'name' => $name,
		'intro' => '<p>Read this before the first session.</p>',
		'introformat' => FORMAT_HTML,
		'content' => $html,
		'contentformat' => FORMAT_HTML,
		'display' => 4,
		'displayoptions' => 0,
		'revision' => 1,
		'printintro' => 0,
		'printlastmodified' => 1,
		'groupmode' => 0,
		'groupingid' => 0,
		'completion' => 0,
		'completionview' => 0,
		'completionexpected' => 0,
		'cmidnumber' => $idnumber,
		'showdescription' => 0,
	];

	add_moduleinfo($moduleinfo, $course);
}

/**
 * Adds a forum to a course section.
 *
 * @param stdClass $course
 * @param int $section
 * @param string $idnumber
 * @param string $name
 * @param string $intro
 * @return void
 */
function moocker_seed_forum(stdClass $course, int $section, string $idnumber, string $name, string $intro): void {
	global $DB;

	$module = $DB->get_record('modules', ['name' => 'forum'], '*', MUST_EXIST);
	$moduleinfo = (object) [
		'modulename' => 'forum',
		'module' => $module->id,
		'course' => $course->id,
		'section' => $section,
		'visible' => 1,
		'visibleoncoursepage' => 1,
		'name' => $name,
		'intro' => $intro,
		'introformat' => FORMAT_HTML,
		'type' => 'general',
		'forcesubscribe' => 0,
		'trackingtype' => 1,
		'blockafter' => 0,
		'blockperiod' => 0,
		'warnafter' => 0,
		'assessed' => 0,
		'scale' => 0,
		'grade' => 0,
		'gradecat' => 0,
		'groupmode' => 0,
		'groupingid' => 0,
		'completion' => 0,
		'completionview' => 0,
		'completionexpected' => 0,
		'cmidnumber' => $idnumber,
		'showdescription' => 0,
	];

	add_moduleinfo($moduleinfo, $course);
}

/**
 * Checks whether one of the demo activities already exists.
 *
 * @param stdClass $course
 * @param string $idnumber
 * @return bool
 */
function moocker_seed_cm_exists(stdClass $course, string $idnumber): bool {
	global $DB;

	return $DB->record_exists('course_modules', [
		'course' => $course->id,
		'idnumber' => $idnumber,
	]);
}

/**
 * Renames a course section when needed.
 *
 * @param stdClass $course
 * @param int $sectionnum
 * @param string $name
 * @return void
 */
function moocker_seed_section_name(stdClass $course, int $sectionnum, string $name): void {
	$section = get_fast_modinfo($course)->get_section_info($sectionnum);
	if ($section && $section->name !== $name) {
		course_update_section($course, $section, ['name' => $name]);
	}
}

/**
 * Returns a user by username, creating it when missing.
 *
 * @param array $profile
 * @return stdClass
 */
function moocker_seed_user(array $profile): stdClass {
	global $CFG, $DB;

	$existing = $DB->get_record('user', [
		'username' => $profile['username'],
		'mnethostid' => $CFG->mnet_localhost_id,
	]);
	if ($existing) {
		return $existing;
	}

	$user = (object) array_merge($profile, [
		'auth' => 'manual',
		'confirmed' => 1,
		'mnethostid' => $CFG->mnet_localhost_id,
		'password' => 'Moocker-Demo-2026!',
		'lang' => 'en',
		'timezone' => '99',
		'description' => '<p>Demo account created by the Moocker screenshot generator.</p>',
		'descriptionformat' => FORMAT_HTML,
	]);
	$user->id = user_create_user($user, true, false);

	return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
}

/**
 * Enrols a user through the manual enrolment plugin.
 *
 * @param stdClass $course
 * @param int $userid
 * @param int $roleid
 * @return void
 */
function moocker_seed_enrol(stdClass $course, int $userid, int $roleid): void {
	global $DB;

	$plugin = enrol_get_plugin('manual');
	if (!$plugin) {
		throw new moodle_exception('Manual enrolment is not enabled');
	}

	$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
	if (!$instance) {
		$instanceid = $plugin->add_instance($course);
		$instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
	}

	if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
		return;
	}

	$plugin->enrol_user($instance, $userid, $roleid);
}

$category = moocker_seed_category();

$courses = [
	moocker_seed_course(
		$category->id,
		'MOOCKER101',
		'Getting Started with Moocker',
		'<p>A hands-on introduction to running Moodle with Docker: containers, persistent data and configuration.</p>'
	),
	moocker_seed_course(
		$category->id,
		'BLEND201',
		'Designing Blended Learning',
		'<p>Plan, build and facilitate courses that mix self-paced activities with live sessions.</p>'
	),
	moocker_seed_course(
		$category->id,
		'ASSESS301',
		'Assessment Essentials',
		'<p>Design fair and effective assessments with quizzes, assignments and rubrics.</p>'
	),
];

$primary = $courses[0];

// Each demo activity is added once and tagged with an idnumber, so reruns are
// idempotent without relying on the presence of Moodle's default forum.
if (!moocker_seed_cm_exists($primary, 'moocker-welcome')) {
	moocker_seed_label(
		$primary,
		0,
		'moocker-welcome',
		'Welcome',
		'<h3>Welcome to the demo course</h3><p>This course is seeded automatically so the README screenshots show realistic content.</p>'
	);
}

if (!moocker_seed_cm_exists($primary, 'moocker-week-1')) {
	moocker_seed_label(
		$primary,
		1,
		'moocker-week-1',
		'Week 1 overview',
		'<h4>Week 1 — Containers and persistence</h4><p>Learn how the web, database and <code>moodledata</code> containers fit together.</p>'
	);
}

if (!moocker_seed_cm_exists($primary, 'moocker-handbook')) {
	moocker_seed_page(
		$primary,
		1,
		'moocker-handbook',
		'Course handbook',
		'<h3>Course handbook</h3><p>In this course you will:</p><ul><li>run Moodle with Docker Compose;</li><li>persist the database and <code>moodledata</code>;</li><li>upgrade the image safely.</li></ul>'
	);
}

if (!moocker_seed_cm_exists($primary, 'moocker-introductions')) {
	moocker_seed_forum(
		$primary,
		1,
		'moocker-introductions',
		'Introduce yourself',
		'<p>Tell the group where you are joining from and what you want to build.</p>'
	);
}

if (!moocker_seed_cm_exists($primary, 'moocker-week-2')) {
	moocker_seed_label(
		$primary,
		2,
		'moocker-week-2',
		'Week 2 overview',
		'<h4>Week 2 — Configuration and backups</h4><p>Environment variables, PHP limits and a repeatable backup routine.</p>'
	);
}

if (!moocker_seed_cm_exists($primary, 'moocker-practice')) {
	moocker_seed_label(
		$primary,
		3,
		'moocker-practice',
		'Practice',
		'<h4>Practice</h4><p>Run the image locally, stop it, restart it and confirm that your data survives.</p>'
	);
}

if (!moocker_seed_cm_exists($primary, 'moocker-resources')) {
	moocker_seed_label(
		$primary,
		4,
		'moocker-resources',
		'Resources',
		'<h4>Resources</h4><p>Links to the Moodle documentation, Docker reference and backup recipes.</p>'
	);
}

moocker_seed_section_name($primary, 1, 'Week 1 — Containers and persistence');
moocker_seed_section_name($primary, 2, 'Week 2 — Configuration and backups');
moocker_seed_section_name($primary, 3, 'Practice');
moocker_seed_section_name($primary, 4, 'Resources');

$admin = get_admin();
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);

$learners = [
	['username' => 'alice.anderson', 'firstname' => 'Alice', 'lastname' => 'Anderson', 'email' => 'alice@example.com', 'city' => 'Lisbon', 'country' => 'PT', 'department' => 'Learning Design'],
	['username' => 'bruno.costa', 'firstname' => 'Bruno', 'lastname' => 'Costa', 'email' => 'bruno@example.com', 'city' => 'Porto', 'country' => 'PT', 'department' => 'Engineering'],
	['username' => 'chen.wei', 'firstname' => 'Chen', 'lastname' => 'Wei', 'email' => 'chen@example.com', 'city' => 'Singapore', 'country' => 'SG', 'department' => 'Platform'],
	['username' => 'dana.okafor', 'firstname' => 'Dana', 'lastname' => 'Okafor', 'email' => 'dana@example.com', 'city' => 'Lagos', 'country' => 'NG', 'department' => 'Faculty'],
	['username' => 'emil.svensson', 'firstname' => 'Emil', 'lastname' => 'Svensson', 'email' => 'emil@example.com', 'city' => 'Stockholm', 'country' => 'SE', 'department' => 'Support'],
];

$users = [];
foreach ($learners as $profile) {
	$users[] = moocker_seed_user($profile);
}

foreach ($courses as $course) {
	moocker_seed_enrol($course, $admin->id, $teacherrole->id);
	foreach ($users as $user) {
		moocker_seed_enrol($course, $user->id, $studentrole->id);
	}
}

// The first-run onboarding tours would cover the pages being captured.
$DB->set_field('tool_usertours_tours', 'enabled', 0);

// The "My courses" page redirects to the dashboard when this is disabled.
set_config('enablemycourses', 1);

purge_all_caches();

echo "Seeded category {$category->name} with " . count($courses) . ' courses and ' . count($users) . " learners.\n";
echo 'CATEGORY_ID=' . $category->id . "\n";
echo 'COURSE_ID=' . $primary->id . "\n";



